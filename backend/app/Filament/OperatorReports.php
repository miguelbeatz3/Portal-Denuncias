<?php
namespace App\Filament;

use App\Models\Report;
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Table;

class OperatorReports extends EntityReports
{
    protected static ?string $title = 'Denúncias atribuídas ao operador';

    public function table(Table $table): Table
    {
        return parent::table($table)->emptyStateHeading('Sem denúncias atribuídas a este operador')
            ->headerActions([
                Action::make('atribuir')->label('Atribuir denúncia')
                    ->visible(fn () => $this->getOwnerRecord()->suspended_at === null)
                    ->schema([
                        Select::make('report_id')->label('Denúncia da entidade')->required()->searchable()
                            ->options(fn () => Report::where('entity_id', $this->getOwnerRecord()->entity_id)
                                ->where('status', 'Em tratamento')->whereNull('operator_id')->orderByDesc('id')
                                ->pluck('reference', 'id')),
                    ])
                    ->action(fn (array $data) => ReportWorkflow::assignOperator($data['report_id'], $this->getOwnerRecord(), auth()->user())),
            ]);
    }
}
