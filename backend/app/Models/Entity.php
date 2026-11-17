<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

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
