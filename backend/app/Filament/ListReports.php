<?php
namespace App\Filament;

use App\Models\{Report, User};
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public function getTabs(): array
    {
        $operator = auth()->user();
        if ($operator->role !== 'entity') {
            return [];
        }

        $tabs = ['todas' => Tab::make('Todas')];
        foreach (self::workViews($operator) as $key => $view) {
            $tabs[$key] = Tab::make($view['label'])->modifyQueryUsing($view['filter']);
        }
        return $tabs;
    }

    public static function workViews(User $operator): array
    {
        return [
            'prazos-vencidos' => ['label' => 'Prazos vencidos', 'colour' => 'danger',
                'filter' => fn (Builder $query) => $query->withOverdueNextAction()->orderBy('reports.next_action_on')],
            'por-iniciar' => ['label' => 'Por iniciar', 'colour' => 'primary',
                'filter' => fn (Builder $query) => $query->whereIn('reports.status', Report::AWAITING_TREATMENT)],
            'por-ler' => ['label' => 'Com mensagens por ler', 'colour' => 'warning',
                'filter' => fn (Builder $query) => $query->whereNull('reports.awaiting_reply_since')
                    ->whereHas('messages', fn (Builder $messages) => $messages->unreadBy($operator))],
            'a-aguardar-resposta' => ['label' => 'A aguardar resposta', 'colour' => 'info',
                'filter' => fn (Builder $query) => $query->where('reports.status', 'Em tratamento')->whereNotNull('reports.awaiting_reply_since')],
            'reencaminhamento-pendente' => ['label' => 'Reencaminhamento pendente', 'colour' => 'info',
                'filter' => fn (Builder $query) => $query->whereHas('pendingReassignmentRequest')],
            'minhas' => ['label' => 'As minhas denúncias', 'colour' => 'gray',
                'filter' => fn (Builder $query) => $query->where('reports.operator_id', $operator->id)],
        ];
    }
}
