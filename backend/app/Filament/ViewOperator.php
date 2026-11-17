<?php
namespace App\Filament;

use App\Models\Operator;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewOperator extends ViewRecord
{
    protected static string $resource = OperatorResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        $actions = OperatorResource::managementActions();
        foreach ($actions as $action) {
            $action->successRedirectUrl(fn () => OperatorResource::getUrl('view', ['record' => $this->getRecord()]));
        }
        return $actions;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entidade e acesso')->schema([
                TextEntry::make('entity.name')->label('Entidade')->placeholder('Por indicar')
                    ->url(fn (Operator $record) => $record->entity_id ? EntityResource::getUrl('view', ['record' => $record->entity_id]) : null),
                TextEntry::make('estado')->label('Estado')->badge()
                    ->state(fn (Operator $record) => $record->suspended_at === null ? 'Activo' : 'Suspenso')
                    ->color(fn (Operator $record) => $record->suspended_at === null ? 'success' : 'danger'),
            ])->columns(2),
            Section::make('Contactos')->schema([
                TextEntry::make('email')->label('E-mail'),
                TextEntry::make('phone')->label('Telefone')->placeholder('Por indicar'),
            ]),
        ]);
    }
}
