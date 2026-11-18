<?php
namespace App\Filament;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntityReports extends RelationManager
{
    protected static string $relationship = 'reports';
    protected static ?string $title = 'Denúncias da entidade';
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'pd-reports-table'])->modifyQueryUsing(fn (Builder $query) => $query->addSelect([
            'classified_as_false' => ReportResource::falseClassification()->limit(1),
        ]))
            ->defaultSort('created_at', 'desc')
            ->columns(ReportResource::reportColumns(showEntity: false))
            ->recordActions([ReportResource::consultAction()])
            ->emptyStateHeading('Sem denúncias atribuídas a esta entidade');
    }
}
