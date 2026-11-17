<?php
namespace App\Filament;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TriageResource extends ReportResource
{
    protected static ?string $slug = 'triagem';
    protected static ?string $pluralModelLabel = 'Por triar';
    protected static ?int $navigationSort = -1;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-stack';

    public static function getNavigationLabel(): string
    {
        return 'Por triar';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', 'Recebida');
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->filters([self::receivedFilter()])->defaultSort('created_at', 'asc')
            ->emptyStateHeading('Sem denúncias por triar');
    }

    public static function getPages(): array
    {
        return ['index' => ListTriage::route('/')];
    }
}
