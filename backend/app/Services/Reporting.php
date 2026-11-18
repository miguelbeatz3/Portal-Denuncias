<?php
namespace App\Services;

use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Reporting
{
    public const GROUPS = ['municipality' => 'Município', 'category' => 'Categoria', 'entity' => 'Entidade'];
    public const STATES = Report::STATES;

    public static function generate(Request $request): array
    {
        $today = CarbonImmutable::today('Africa/Luanda');
        $filters = array_replace([
            'from' => $today->subDays(29)->toDateString(), 'until' => $today->toDateString(),
            'group' => 'municipality', 'municipality' => '', 'category' => '', 'entity' => '',
        ], $request->only(['from', 'until', 'group', 'municipality', 'category', 'entity']));
        $user = auth()->user();
        abort_unless($user && ($user->role === 'admin' || ($user->role === 'entity' && $user->entity_id)), 403);
        $administrative = $user->role === 'admin';
        if (!$administrative) $filters['entity'] = $user->entity_id;
        $validation = Validator::make($filters, [
            'from' => 'required|date_format:Y-m-d', 'until' => 'required|date_format:Y-m-d|after_or_equal:from',
            'group' => ['required', Rule::in(array_keys(self::GROUPS))],
            'municipality' => 'nullable|string|max:100', 'category' => 'nullable|string|max:100', 'entity' => 'nullable|integer|min:1',
        ]);
        if ($validation->fails()) {
            return ['filters' => array_map(fn ($v) => is_scalar($v) ? $v : '', $filters), 'error' => 'Verifique os filtros e indique um intervalo de datas válido.'];
        }
        $query = Report::query()->leftJoin('entities', 'reports.entity_id', '=', 'entities.id')
            ->where('reports.created_at', '>=', CarbonImmutable::parse($filters['from'], 'Africa/Luanda')->utc())
            ->where('reports.created_at', '<', CarbonImmutable::parse($filters['until'], 'Africa/Luanda')->addDay()->utc());
        foreach (['municipality' => 'reports.municipality', 'category' => 'reports.category', 'entity' => 'reports.entity_id'] as $key => $column) {
            if (filled($filters[$key])) $query->where($column, $filters[$key]);
        }
        $dimension = match ($filters['group']) {
            'entity' => 'entities.name', 'category' => 'reports.category', default => 'reports.municipality',
        };
        $missing = match ($filters['group']) {
            'entity' => 'Por atribuir', 'category' => 'Por classificar', default => 'Por indicar',
        };
        $query->selectRaw("$dimension as dimension, reports.status, count(*) as amount");
        if ($administrative) {
            $query->selectRaw('sum(case when reports.status = ? and exists (
                select 1 from report_events where report_events.report_id = reports.id and false_report_reason is not null
            ) then 1 else 0 end) as false_count', ['Arquivada']);
        }
        $counts = $query->groupBy($dimension, 'reports.status')->orderBy($dimension)->toBase()->get();
        $totals = array_fill_keys(array_keys(self::STATES), 0);
        $rows = [];
        foreach ($counts->groupBy('dimension') as $group) {
            $states = array_fill_keys(array_keys(self::STATES), 0);
            foreach ($group as $entry) {
                $states[$entry->status] = (int) $entry->amount;
                $totals[$entry->status] += (int) $entry->amount;
            }
            $rows[] = ['label' => $group->first()->dimension ?? $missing, 'states' => $states,
                'false_count' => (int) $group->sum('false_count'), 'total' => array_sum($states)];
        }
        return compact('filters', 'rows', 'totals', 'administrative') + ['falseTotal' => array_sum(array_column($rows, 'false_count')), 'error' => null];
    }
}
