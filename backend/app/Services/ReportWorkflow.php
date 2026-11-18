<?php
namespace App\Services;

use App\Models\{Administrator, Category, Entity, Operator, Report, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportWorkflow
{
    public static function assignOperator(int $reportId, Operator $operator, User $actor): void
    {
        abort_unless($actor->role === 'admin', 403);
        DB::transaction(function () use ($reportId, $operator) {
            $operator = Operator::lockForUpdate()->findOrFail($operator->id);
            $updated = $operator->entity_id && $operator->suspended_at === null && Report::whereKey($reportId)
                ->where('entity_id', $operator->entity_id)->whereIn('status', Report::ACTIVE)
                ->whereNull('operator_id')->update(['operator_id' => $operator->id]);
            if (!$updated) {
                throw ValidationException::withMessages(['report_id' => 'A denúncia já não está disponível para este operador.']);
            }
        });
    }

    public static function assign(Report $report, User $actor, array $data): void
    {
        abort_unless($actor->role === 'admin', 403);
        DB::transaction(function () use ($report, $actor, $data) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            self::requireStatus($current, 'Recebida');
            $entity = Entity::receiving()->lockForUpdate()->find($data['entity_id']);
            if (!$entity) {
                throw ValidationException::withMessages(['entity_id' => 'Seleccione uma entidade que recebe denúncias.']);
            }
            $category = Category::where('active', true)->where('name', $data['category'] ?? null)->sharedLock()->value('name');
            if ($category === null) {
                throw ValidationException::withMessages(['category' => 'Seleccione uma categoria disponível.']);
            }
            $current->update(['category' => $category, 'entity_id' => $entity->id, 'status' => 'Encaminhada']);
            self::record($current, $actor, 'Encaminhada para '.$entity->name.'.');
        });
    }

    public static function reassign(Report $report, User $actor, array $data): void
    {
        abort_unless($actor->role === 'admin', 403);
        $reason = self::validateReason($data['reason'] ?? '');
        DB::transaction(function () use ($report, $actor, $data, $reason) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            self::requireStatus($current, Report::ACTIVE);
            if ((int) $current->entity_id !== (int) $data['previous_entity_id']) {
                throw ValidationException::withMessages(['entity_id' => 'A entidade responsável mudou. Volte a abrir o reencaminhamento.']);
            }
            $pending = $current->pendingReassignmentRequest()->first();
            if (array_key_exists('request_id', $data) && (int) $data['request_id'] !== (int) $pending?->id) {
                throw ValidationException::withMessages(['entity_id' => 'O pedido mudou. Volte a abrir o reencaminhamento.']);
            }
            $destination = Entity::receiving()->whereKeyNot($current->entity_id)->lockForUpdate()->find($data['entity_id']);
            if (!$destination) {
                throw ValidationException::withMessages(['entity_id' => 'Seleccione outra entidade que receba denúncias.']);
            }
            $origin = $current->entity()->firstOrFail();
            $current->update(['entity_id' => $destination->id, 'operator_id' => null, 'status' => 'Reencaminhada']);
            self::resolveReassignmentRequest($current, $actor, 'Reencaminhada');
            DB::table('report_events')->insert([
                'report_id' => $current->id, 'user_id' => $actor->id, 'created_at' => now(),
                'message' => 'Reencaminhada para '.$destination->name.'.',
                'previous_entity_name' => $origin->name, 'reassignment_reason' => $reason,
            ]);
        });
    }

    public static function startTreatment(Report $report, User $actor): void
    {
        DB::transaction(function () use ($report, $actor) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, Report::AWAITING_TREATMENT);
            $current->update(['status' => 'Em tratamento']);
            self::record($current, $actor, 'A entidade iniciou o tratamento da denúncia.');
        });
    }

    public static function requestReassignment(Report $report, User $actor, array $data): void
    {
        DB::transaction(function () use ($report, $actor, $data) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, Report::ACTIVE);
            $reason = self::validateReason($data['reason'] ?? '');
            if ($current->pendingReassignmentRequest()->exists()) {
                throw ValidationException::withMessages(['reason' => 'Já existe um pedido a aguardar decisão.']);
            }
            $suggested = $data['suggested_entity_id'] ?? null;
            if (filled($suggested) && !Entity::receiving()->whereKeyNot($current->entity_id)->whereKey($suggested)->sharedLock()->first()) {
                throw ValidationException::withMessages(['suggested_entity_id' => 'Seleccione outra entidade que receba denúncias.']);
            }
            $current->reassignmentRequests()->create(['entity_id' => $current->entity_id,
                'suggested_entity_id' => filled($suggested) ? $suggested : null, 'requested_by' => $actor->id, 'reason' => $reason]);
        });
    }

    public static function declineReassignment(Report $report, User $actor, int $requestId, string $reason): void
    {
        abort_unless($actor->role === 'admin', 403);
        $reason = self::validateReason($reason);
        DB::transaction(function () use ($report, $actor, $requestId, $reason) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            self::requireStatus($current, Report::ACTIVE);
            $pendingId = $current->pendingReassignmentRequest()->value('id');
            if ($pendingId === null || (int) $pendingId !== $requestId) {
                throw ValidationException::withMessages(['reason' => 'O pedido mudou. Volte a abrir a ficha da denúncia.']);
            }
            self::resolveReassignmentRequest($current, $actor, 'Recusado', $reason);
        });
    }

    public static function classifyAsFalse(Report $report, User $actor, string $reason): void
    {
        self::archive($report, $actor, $reason, falseReport: true);
    }

    public static function archive(Report $report, User $actor, string $reason, bool $falseReport = false): void
    {
        abort_unless($actor->role === 'admin', 403);
        $reason = self::validateReason($reason);
        DB::transaction(function () use ($report, $actor, $reason, $falseReport) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            if ($current->status === 'Arquivada' || ($falseReport && $current->status === 'Concluída')) {
                throw ValidationException::withMessages(['reason' => 'Esta operação já não está disponível para a denúncia.']);
            }
            $current->update(['status' => 'Arquivada']);
            self::resolveReassignmentRequest($current, $actor, 'Encerrado por arquivo');
            DB::table('report_events')->insert([
                'report_id' => $current->id, 'user_id' => $actor->id, 'created_at' => now(),
                'message' => 'Denúncia arquivada.',
                ($falseReport ? 'false_report_reason' : 'archive_reason') => $reason,
            ]);
        });
    }

    public static function publish(Report $report, User $actor, array $data): void
    {
        DB::transaction(function () use ($report, $actor, $data) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, 'Em tratamento');
            if ($data['close'] ?? false) {
                $current->update(['status' => 'Concluída']);
                self::resolveReassignmentRequest($current, $actor, 'Encerrado por conclusão');
            }
            self::record($current, $actor, $data['message']);
        });
    }

    public static function sendMessage(Report $report, User $actor, string $message): void
    {
        DB::transaction(function () use ($report, $actor, $message) {
            if ($actor->role === 'admin') {
                $admin = Administrator::whereKey($actor->id)->whereNull('suspended_at')
                    ->where('session_version', (int) $actor->session_version)->sharedLock()->first();
                abort_unless($admin, 403);
                $current = Report::lockForUpdate()->findOrFail($report->id);
            } else {
                $current = self::lockEntityReport($report, $actor);
            }
            $message = trim($message);
            if ($message === '' || mb_strlen($message) > 2000) {
                throw ValidationException::withMessages(['message' => 'Escreva uma mensagem até 2000 caracteres.']);
            }
            $current->messages()->create(['user_id' => $actor->id, 'sender' => $actor->role, 'message' => $message]);
        });
    }

    private static function lockEntityReport(Report $report, User $actor): Report
    {
        abort_unless($actor->role === 'entity' && $actor->entity_id, 403);
        $operator = Operator::whereKey($actor->id)->whereNull('suspended_at')
            ->where('session_version', (int) $actor->session_version)->sharedLock()->first();
        abort_unless($operator?->entity_id, 403);
        $current = Report::lockForUpdate()->findOrFail($report->id);
        abort_unless((int) $current->entity_id === (int) $operator->entity_id, 403);
        return $current;
    }

    private static function validateReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Indique um motivo até 2000 caracteres.']);
        }
        return $reason;
    }

    private static function resolveReassignmentRequest(Report $report, User $actor, string $resolution, ?string $response = null): void
    {
        $report->pendingReassignmentRequest()->update(['resolution' => $resolution, 'response' => $response,
            'resolved_by' => $actor->id, 'resolved_at' => now()]);
    }

    private static function requireStatus(Report $report, string|array $expected): void
    {
        if (!in_array($report->status, (array) $expected, true)) {
            throw ValidationException::withMessages(['status' => 'O estado mudou. Actualize a lista.']);
        }
    }

    private static function record(Report $report, User $actor, string $message): void
    {
        DB::table('report_events')->insert(['report_id' => $report->id, 'user_id' => $actor->id,
            'message' => $message, 'created_at' => now()]);
    }
}
