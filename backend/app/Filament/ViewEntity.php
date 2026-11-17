<?php
namespace App\Filament;

use App\Models\Entity;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewEntity extends ViewRecord
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return EntityResource::managementActions();
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificação e localização')->schema([
                TextEntry::make('accepts_reports')->label('Estado')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Activa' : 'Inactiva')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextEntry::make('service_scope')->label('Âmbito de actuação')->placeholder('Por indicar')
                    ->formatStateUsing(fn (string $state) => Entity::SCOPES[$state] ?? $state),
                TextEntry::make('municipality.name')->label('Município')->placeholder('Por indicar'),
                TextEntry::make('address')->label('Morada')->placeholder('Por indicar'),
            ])->columns(2),
            Section::make('Contactos')->schema([
                TextEntry::make('phone')->label('Telefone')->placeholder('Por indicar'),
                TextEntry::make('email')->label('E-mail')->placeholder('Por indicar'),
            ]),
        ]);
    }
}
