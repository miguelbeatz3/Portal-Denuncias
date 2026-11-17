<?php
namespace App\Filament;

use Filament\Actions\{CreateAction, EditAction};
use Filament\Forms\Components\{Textarea, TextInput, Toggle};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Table;

abstract class CatalogueResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(100)->unique(ignoreRecord: true),
            ...(static::getModel() === \App\Models\Category::class ? [
                Textarea::make('description')->label('Descrição')->maxLength(255)->rows(2)
                    ->helperText('Esta descrição aparece quando o cidadão selecciona a categoria.'),
            ] : []),
            Toggle::make('active')->label('Disponível para selecção')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            ...(static::getModel() === \App\Models\Category::class ? [
                TextColumn::make('description')->label('Descrição')->wrap(),
            ] : []),
            IconColumn::make('active')->label('Disponível')->boolean(),
        ])->headerActions([CreateAction::make()->label('Adicionar')])
            ->recordActions([
                EditAction::make()->label('Editar')->icon(Heroicon::OutlinedPencilSquare)->iconButton()->tooltip('Editar'),
            ]);
    }
}
