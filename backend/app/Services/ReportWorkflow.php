<?php
namespace App\Services;

use App\Models\{Category, Entity, Operator, Report, User};
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
                ->where('entity_id', $operator->entity_id)->where('status', 'Em tratamento')
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
            $current->update(['category' => $category, 'entity_id' => $entity->id, 'status' => 'Em tratamento']);
            self::record($current, $actor, 'Encaminhada para '.$entity->name.'.');
        });
    }

    public static function reassign(Report $report, User $actor, array $data): void
    {
        abort_unless($actor->role === 'admin', 403);
        $reason = trim($data['reason'] ?? '');
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Indique um motivo até 2000 caracteres.']);
        }
        DB::transaction(function () use ($report, $actor, $data, $reason) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            self::requireStatus($current, 'Em tratamento');
            if ((int) $current->entity_id !== (int) $data['previous_entity_id']) {
                throw ValidationException::withMessages(['entity_id' => 'A entidade responsável mudou. Volte a abrir o reencaminhamento.']);
            }
            $destination = Entity::receiving()->whereKeyNot($current->entity_id)->lockForUpdate()->find($data['entity_id']);
            if (!$destination) {
                throw ValidationException::withMessages(['entity_id' => 'Seleccione outra entidade que receba denúncias.']);
            }
            $origin = $current->entity()->firstOrFail();
            $current->update(['entity_id' => $destination->id, 'operator_id' => null]);
            DB::table('report_events')->insert([
                'report_id' => $current->id, 'user_id' => $actor->id, 'created_at' => now(),
                'message' => 'Reencaminhada para '.$destination->name.'.',
                'previous_entity_name' => $origin->name, 'reassignment_reason' => $reason,
            ]);
        });
    }

    public static function classifyAsFalse(Report $report, User $actor, string $reason): void
    {
        self::archive($report, $actor, $reason, falseReport: true);
    }

    public static function archive(Report $report, User $actor, string $reason, bool $falseReport = false): void
    {
        abort_unless($actor->role === 'admin', 403);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Indique um motivo até 2000 caracteres.']);
        }
        DB::transaction(function () use ($report, $actor, $reason, $falseReport) {
            $current = Report::lockForUpdate()->findOrFail($report->id);
            if ($current->status === 'Arquivada' || ($falseReport && $current->status === 'Concluída')) {
                throw ValidationException::withMessages(['reason' => 'Esta operação já não está disponível para a denúncia.']);
            }
            $current->update(['status' => 'Arquivada']);
            DB::table('report_events')->insert([
                'report_id' => $current->id, 'user_id' => $actor->id, 'created_at' => now(),
                'message' => 'Denúncia arquivada.',
                ($falseReport ? 'false_report_reason' : 'archive_reason') => $reason,
            ]);
        });
    }

    public static function publish(Report $report, User $actor, array $data): void
    {
        abort_unless($actor->role === 'entity' && $actor->entity_id, 403);
        DB::transaction(function () use ($report, $actor, $data) {
            $operator = Operator::whereKey($actor->id)->whereNull('suspended_at')
                ->where('session_version', (int) $actor->session_version)->sharedLock()->first();
            abort_unless($operator, 403);
            $current = Report::lockForUpdate()->findOrFail($report->id);
            abort_unless((int) $current->entity_id === (int) $operator->entity_id, 403);
            self::requireStatus($current, 'Em tratamento');
            if ($data['close'] ?? false) {
                $current->update(['status' => 'Concluída']);
            }
            self::record($current, $actor, $data['message']);
        });
    }

    private static function requireStatus(Report $report, string $expected): void
    {
        if ($report->status !== $expected) {
            throw ValidationException::withMessages(['status' => 'O estado mudou. Actualize a lista.']);
        }
    }

    private static function record(Report $report, User $actor, string $message): void
    {
        DB::table('report_events')->insert(['report_id' => $report->id, 'user_id' => $actor->id,
            'message' => $message, 'created_at' => now()]);
    }
}
