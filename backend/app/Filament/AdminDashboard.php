<?php
namespace App\Filament;

use App\Models\Report;
use Filament\Pages\Dashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

class AdminDashboard extends Dashboard
{
    protected static ?string $title = 'Visão geral';
    protected string $view = 'panels.dashboard';
    #[Url] public string $period = '30';
    #[Url] public string $from = '';
    #[Url] public string $until = '';

    private const PERIODS = [
        'today' => 'Hoje', 'yesterday' => 'Ontem', 'month' => 'Mês Atual', '30' => 'Últimos 30 dias',
        '90' => 'Últimos 90 dias', '180' => 'Últimos 180 dias', 'year' => 'Este Ano', 'custom' => 'Personalizado',
    ];

    protected function getViewData(): array
    {
        $user = auth()->user();
        $admin = $user->role === 'admin';
        $workCards = [];
        if (!$admin) {
            $work = ReportResource::getEloquentQuery();
            foreach (ListReports::workViews($user) as $tab => $view) {
                $workCards[] = ['label' => $view['label'], 'colour' => $view['colour'],
                    'total' => $view['filter'](clone $work)->count(),
                    'url' => ReportResource::getUrl('index', ['tab' => $tab])];
            }
        }
        $today = CarbonImmutable::today('Africa/Luanda');
        $end = $this->period === 'yesterday' ? $today->subDay() : $today;
        $start = match ($this->period) {
            'today', 'yesterday' => $end,
            'month' => $today->startOfMonth(),
            'year' => $today->startOfYear(),
            '90', '180' => $today->subDays((int) $this->period - 1),
            default => $today->subDays(29),
        };
        if ($this->period === 'custom') {
            $invalid = Validator::make(['from' => $this->from, 'until' => $this->until], [
                'from' => 'required|date_format:Y-m-d', 'until' => 'required|date_format:Y-m-d|after_or_equal:from',
            ])->fails();
            if ($invalid) return ['workCards' => $workCards, 'periods' => self::PERIODS,
                'rangeError' => 'Indique as duas datas. A data final deve ser igual ou posterior à inicial.'];
            $start = CarbonImmutable::parse($this->from, 'Africa/Luanda');
            $end = CarbonImmutable::parse($this->until, 'Africa/Luanda');
        }
        $base = Report::where('created_at', '>=', $start->utc())->where('created_at', '<', $end->addDay()->utc());
        if (!$admin) {
            $base->whereNotNull('entity_id')->where('entity_id', $user->entity_id);
        }
        $dates = ['received' => ['from' => $start->toDateString(), 'until' => $end->toDateString()]];
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $cards = [];
        foreach (Report::STATES as $status => $label) {
            if (!$admin && $status === 'Recebida') continue;
            $cards[] = [
                'label' => $label, 'total' => $counts[$status] ?? 0, 'colour' => ReportResource::badgeColor($status),
                'url' => $status === 'Recebida' ? TriageResource::getUrl('index', ['filters' => $dates]) : ReportResource::getUrl('index', ['filters' => $dates + ['status' => ['value' => $status]]]),
            ];
        }
        $days = (int) $start->diffInDays($end) + 1;
        $step = (int) ceil($days / 31);
        $hourly = $days === 1;
        $chart = [];
        if ($this->period === 'year') {
            for ($month = $start; $month <= $end; $month = $month->addMonth()) {
                $chart[] = ['label' => $month->locale('pt')->translatedFormat('F'), 'total' => 0];
            }
        } else {
            for ($i = 0; $i < ($hourly ? 24 : $days); $i += $step) {
                $first = $start->addDays($i);
                $last = $start->addDays(min($i + $step, $days) - 1);
                $label = $hourly ? sprintf('%02dh', $i) : $first->format('d/m/Y').($step > 1 ? ' – '.$last->format('d/m/Y') : '');
                $chart[] = ['label' => $label, 'total' => 0];
            }
        }
        // Aggregate in UTC hours, then assign each bucket to its local hour or day.
        $hour = $base->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00', created_at)" : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
        foreach ((clone $base)->selectRaw("$hour as bucket, count(*) as total")->groupBy('bucket')->toBase()->get() as $row) {
            $local = CarbonImmutable::parse($row->bucket, 'UTC')->setTimezone('Africa/Luanda');
            $index = match (true) {
                $this->period === 'year' => $local->month - 1,
                $hourly => $local->hour,
                default => intdiv((int) $start->diffInDays($local->startOfDay()), $step),
            };
            $chart[$index]['total'] += $row->total;
        }
        $totals = array_column($chart, 'total');
        $peak = max($totals);
        $tickStep = max(1, (int) ceil($peak / 4));
        $chartMax = max(1, (int) ceil($peak / $tickStep)) * $tickStep;
        foreach ($chart as $index => $point) {
            $chart[$index]['x'] = count($chart) === 1 ? 500 : round(5 + 990 * $index / (count($chart) - 1), 2);
            $chart[$index]['y'] = round(155 - 150 * $point['total'] / $chartMax, 2);
        }
        return [
            'periods' => self::PERIODS, 'rangeError' => null, 'dates' => $dates, 'chart' => $chart,
            'chartMax' => $chartMax, 'ticks' => range(0, $chartMax, $tickStep),
            'peakIndex' => array_search($peak, $totals), 'receivedTotal' => array_sum($totals),
            'rangeLabel' => $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            'cards' => $cards, 'workCards' => $workCards,
            'admin' => $admin,
            'pendingReassignments' => $admin ? Report::whereHas('pendingReassignmentRequest')->count() : 0,
            'queue' => (clone $base)->whereIn('status', $admin ? ['Recebida'] : Report::ACTIVE)
                ->when(!$admin, fn ($query) => $query->whereNull('awaiting_reply_since'))->oldest()->orderBy('id')->limit(5)->get(),
            'workload' => $admin ? (clone $base)->where('status', 'Em tratamento')->whereNotNull('entity_id')
                ->selectRaw('entity_id, count(*) as total')->with('entity:id,name')
                ->groupBy('entity_id')->orderByDesc('total')->orderBy('entity_id')->limit(5)->get() : null,
        ];
    }
}
