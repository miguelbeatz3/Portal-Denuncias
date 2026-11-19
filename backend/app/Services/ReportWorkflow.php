<?php
namespace App\Services;

use App\Models\{Administrator, Category, Entity, Operator, Report, ReportInternalNote, User};
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

    public static function distributeOperator(Report $report, User $actor, int $operatorId, ?int $previousOperatorId): void
    {
        abort_unless($actor->role === 'entity', 403);
        DB::transaction(function () use ($report, $actor, $operatorId, $previousOperatorId) {
            $operators = Operator::whereKey([$actor->id, $operatorId])->whereNull('suspended_at')
                ->orderBy('id')->sharedLock()->get()->keyBy('id');
            $manager = $operators->get($actor->id);
            abort_unless($manager?->entity_id && (int) $manager->session_version === (int) $actor->session_version, 403);

            $current = Report::where('entity_id', $manager->entity_id)->lockForUpdate()->find($report->id);
            abort_unless($current, 403);
            $responsibleId = Entity::whereKey($manager->entity_id)->sharedLock()->value('responsible_operator_id');
            abort_unless((int) $responsibleId === (int) $manager->id, 403);

            if (!in_array($current->status, Report::ACTIVE, true)) {
                throw ValidationException::withMessages(['operator_id' => 'Só é possível distribuir denúncias em aberto.']);
            }
            if ((int) $current->operator_id !== (int) $previousOperatorId) {
                throw ValidationException::withMessages(['operator_id' => 'A atribuição mudou. Feche e volte a abrir esta acção.']);
            }
            $destination = $operators->get($operatorId);
            if (!$destination || (int) $destination->entity_id !== (int) $manager->entity_id || $operatorId === $previousOperatorId) {
                throw ValidationException::withMessages(['operator_id' => 'Seleccione outro operador activo desta entidade.']);
            }

            $current->update(['operator_id' => $destination->id]);
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
            $current->update(['entity_id' => $destination->id, 'operator_id' => null,
                'status' => 'Reencaminhada', 'awaiting_reply_since' => null, 'next_action_on' => null]);
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

    public static function setAwaitingReply(Report $report, User $actor, bool $awaiting): void
    {
        DB::transaction(function () use ($report, $actor, $awaiting) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, 'Em tratamento');
            if ($current->isAwaitingReply() !== $awaiting) {
                $current->update(['awaiting_reply_since' => $awaiting ? now() : null]);
            }
        });
    }

    public static function scheduleNextAction(Report $report, User $actor, ?string $date): void
    {
        DB::transaction(function () use ($report, $actor, $date) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, Report::ACTIVE);
            $date = filled($date) ? $date : null;
            validator(['next_action_on' => $date], ['next_action_on' => 'nullable|date_format:Y-m-d'],
                ['next_action_on.date_format' => 'Indique uma data válida para a próxima acção.'])->validate();
            $current->update(['next_action_on' => $date]);
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
        $reason = self::validateReason($reason);
        DB::transaction(function () use ($report, $actor, $reason, $falseReport) {
            $current = self::lockStaffReport($report, $actor);
            if (!self::canArchive($current, $actor, $falseReport)) {
                throw ValidationException::withMessages(['reason' => 'Esta operação já não está disponível para a denúncia.']);
            }
            $current->update(['status' => 'Arquivada', 'awaiting_reply_since' => null, 'next_action_on' => null]);
            self::resolveReassignmentRequest($current, $actor, 'Encerrado por arquivo');
            DB::table('report_events')->insert([
                'report_id' => $current->id, 'user_id' => $actor->id, 'created_at' => now(),
                'message' => 'Denúncia arquivada.',
                ($falseReport ? 'false_report_reason' : 'archive_reason') => $reason,
            ]);
        });
    }

    public static function canArchive(Report $report, User $actor, bool $falseReport = false): bool
    {
        $eligible = $falseReport ? Report::ACTIVE : [...Report::ACTIVE, 'Concluída'];
        return match ($actor->role) {
            'admin' => in_array($report->status, ['Recebida', ...$eligible], true),
            'entity' => $actor->entity_id && (int) $report->entity_id === (int) $actor->entity_id
                && in_array($report->status, $eligible, true),
            default => false,
        };
    }

    public static function conclude(Report $report, User $actor, string $verdict): void
    {
        DB::transaction(function () use ($report, $actor, $verdict) {
            $current = self::lockEntityReport($report, $actor);
            self::requireStatus($current, 'Em tratamento');
            $verdict = trim($verdict);
            if ($verdict === '' || mb_strlen($verdict) > 2000) {
                throw ValidationException::withMessages(['verdict' => 'Indique o veredito final até 2000 caracteres.']);
            }
            $current->update(['status' => 'Concluída', 'final_verdict' => $verdict, 'awaiting_reply_since' => null, 'next_action_on' => null]);
            self::resolveReassignmentRequest($current, $actor, 'Encerrado por conclusão');
            self::record($current, $actor, 'Denúncia concluída.');
        });
    }

    public static function sendMessage(Report $report, User $actor, string $message): void
    {
        DB::transaction(function () use ($report, $actor, $message) {
            $current = self::lockStaffReport($report, $actor);
            $message = trim($message);
            if ($message === '' || mb_strlen($message) > 2000) {
                throw ValidationException::withMessages(['message' => 'Escreva uma mensagem até 2000 caracteres.']);
            }
            $current->messages()->create(['user_id' => $actor->id, 'sender' => $actor->role, 'message' => $message,
                'sender_entity_name' => $actor->role === 'entity' ? $current->entity()->firstOrFail()->name : null]);
        });
    }

    public static function markMessagesRead(Report $report, User $operator, int $lastMessageId): void
    {
        DB::transaction(function () use ($report, $operator, $lastMessageId) {
            $current = self::lockEntityReport($report, $operator);
            $key = ['report_id' => $current->id, 'operator_id' => $operator->id];
            $reads = DB::table('report_message_reads');
            if ((int) (clone $reads)->where($key)->value('last_read_message_id') < $lastMessageId) {
                $reads->updateOrInsert($key, ['last_read_message_id' => $lastMessageId]);
            }
        });
    }

    public static function addInternalNote(Report $report, User $actor, string $body): void
    {
        DB::transaction(function () use ($report, $actor, $body) {
            $current = self::lockStaffReport($report, $actor);
            $body = trim($body);
            if ($body === '' || mb_strlen($body) > ReportInternalNote::MAX_LENGTH) {
                throw ValidationException::withMessages(['body' => 'Escreva uma nota interna até '.ReportInternalNote::MAX_LENGTH.' caracteres.']);
            }
            $current->internalNotes()->create(['author_id' => $actor->id, 'author_name' => $actor->name, 'body' => $body]);
        });
    }

    private static function lockStaffReport(Report $report, User $actor): Report
    {
        if ($actor->role !== 'admin') {
            return self::lockEntityReport($report, $actor);
        }
        $admin = Administrator::whereKey($actor->id)->whereNull('suspended_at')
            ->where('session_version', (int) $actor->session_version)->sharedLock()->first();
        abort_unless($admin, 403);
        return Report::lockForUpdate()->findOrFail($report->id);
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
