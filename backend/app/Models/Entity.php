<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Entity extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'accepts_reports', 'municipality_id', 'service_scope', 'address', 'phone', 'email'];
    public const SCOPES = ['municipal' => 'Municipal', 'general' => 'Geral ou especializado'];
    protected $casts = ['accepts_reports' => 'boolean'];

    public function scopeReceiving(Builder $query): Builder
    {
        return $query->where('accepts_reports', true);
    }

    public function municipality(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function responsibleOperator(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'responsible_operator_id');
    }

    public function setResponsibleOperator(?int $operatorId, User $actor): void
    {
        abort_unless($actor->role === 'admin', 403);
        DB::transaction(function () use ($operatorId, $actor) {
            $admin = Administrator::whereKey($actor->id)->whereNull('suspended_at')
                ->where('session_version', (int) $actor->session_version)->sharedLock()->first();
            abort_unless($admin, 403);
            if ($operatorId !== null && !Operator::activeInEntity($this->id)->sharedLock()->find($operatorId)) {
                throw ValidationException::withMessages(['responsible_operator_id' => 'Seleccione um operador activo desta entidade.']);
            }
            $current = static::lockForUpdate()->findOrFail($this->id);
            $current->responsible_operator_id = $operatorId;
            $current->save();
        });
        $this->refresh();
    }

    public static function destinations(Report $report): array
    {
        $local = 'Sugeridas para '.$report->municipality;
        $general = 'Entidades de âmbito geral ou especializado';
        $other = 'Outras entidades disponíveis';
        $groups = [$local => [], $general => [], $other => []];
        $query = static::receiving()->with('municipality')->orderBy('name');
        if ($report->entity_id !== null) {
            $query->whereKeyNot($report->entity_id);
        }
        foreach ($query->get() as $entity) {
            $municipality = $entity->municipality?->name;
            $group = match (true) {
                $entity->service_scope === 'general' => $general,
                $entity->service_scope === 'municipal' && $municipality !== null && $municipality === $report->municipality => $local,
                default => $other,
            };
            $groups[$group][$entity->id] = $municipality ? $entity->name.' · '.$municipality : $entity->name;
        }
        return array_filter($groups);
    }
}
