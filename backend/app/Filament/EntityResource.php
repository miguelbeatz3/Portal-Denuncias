<?php
namespace App\Filament;

use App\Models\{Entity, Operator};
use Filament\Actions\{Action, CreateAction, EditAction};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;
    protected static ?string $modelLabel = 'Entidade';
    protected static ?string $pluralModelLabel = 'Entidades';
    protected static ?string $slug = 'entidades';
    protected static string | \UnitEnum | null $navigationGroup = 'Entidades e acessos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome da entidade')->placeholder('Ex.: Administração Municipal de Viana')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('service_scope')->label('Âmbito de actuação')->placeholder('Seleccione o âmbito de actuação')
                ->options(Entity::SCOPES)->required()->live(),
            Select::make('municipality_id')->label(fn (Get $get) => $get('service_scope') === 'municipal' ? 'Município' : 'Município (opcional)')
                ->placeholder('Seleccione o município')->searchPrompt('Escreva o nome do município')
                ->relationship('municipality', 'name')
                ->searchable()->preload()->required(fn (Get $get) => $get('service_scope') === 'municipal')
                ->exists('municipalities', 'id'),
            TextInput::make('address')->label('Morada (opcional)')->placeholder('Ex.: Rua 5, casa 12, bairro Estalagem')
                ->maxLength(255),
            TextInput::make('phone')->label('Telefone de contacto (opcional)')->placeholder('Ex.: +244 9XX XXX XXX')
                ->tel()->maxLength(32),
            TextInput::make('email')->label('E-mail de contacto (opcional)')->placeholder('Ex.: atendimento@entidade.ao')
                ->email()->maxLength(255),
            Toggle::make('accepts_reports')->label('Entidade activa')->default(true)
                ->helperText('Uma entidade activa pode receber denúncias por encaminhamento ou reencaminhamento.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nome')->searchable()->sortable()->wrap(),
            TextColumn::make('municipality.name')->label('Município')->placeholder('Por indicar')->searchable()->wrap(),
            TextColumn::make('service_scope')->label('Âmbito')->placeholder('Por indicar')
                ->formatStateUsing(fn (string $state) => Entity::SCOPES[$state] ?? $state)->wrap(),
            TextColumn::make('accepts_reports')->label('Estado')->badge()
                ->formatStateUsing(fn (bool $state) => $state ? 'Activa' : 'Inactiva')
                ->color(fn (bool $state) => $state ? 'success' : 'gray'),
        ])->headerActions([CreateAction::make()->label('Criar entidade')])
            ->recordActions([
                Action::make('consultar')->label('Consultar')->icon(Heroicon::OutlinedEye)->iconButton()->tooltip('Consultar')
                    ->url(fn (Entity $record) => static::getUrl('view', ['record' => $record])),
                ...array_map(fn (Action $action) => $action->iconButton()->tooltip($action->getLabel()), static::managementActions()),
            ]);
    }

    public static function managementActions(): array
    {
        return [
            EditAction::make()->label('Editar')->icon(Heroicon::OutlinedPencilSquare),
            Action::make('definirResponsavel')->label('Definir responsável')->icon(Heroicon::OutlinedUserCircle)->button()
                ->modalDescription('O responsável pode atribuir e reatribuir denúncias aos operadores da entidade.')
                ->fillForm(fn (Entity $record) => ['responsible_operator_id' => $record->responsible_operator_id])
                ->schema([
                    Select::make('responsible_operator_id')->label('Operador responsável')->searchable()
                        ->placeholder('Sem responsável')
                        ->options(fn (Entity $record) => Operator::activeInEntity($record->id)->orderBy('name')->pluck('name', 'id')),
                ])
                ->successNotificationTitle('Responsável actualizado')
                ->action(fn (Entity $record, array $data) => $record->setResponsibleOperator($data['responsible_operator_id'] ?? null, auth()->user())),
            Action::make('desactivar')->label('Desactivar entidade')->icon(Heroicon::OutlinedPauseCircle)->color('warning')
                ->visible(fn (Entity $record) => $record->accepts_reports)
                ->requiresConfirmation()->modalHeading(fn (Entity $record) => 'Desactivar '.$record->name.'?')
                ->modalDescription('A entidade deixa de receber novos encaminhamentos. As denúncias já atribuídas continuam disponíveis para tratamento.')
                ->modalSubmitActionLabel('Desactivar')
                ->action(fn (Entity $record) => $record->update(['accepts_reports' => false])),
            Action::make('activar')->label('Activar entidade')->icon(Heroicon::OutlinedPlayCircle)->color('success')
                ->visible(fn (Entity $record) => ! $record->accepts_reports)
                ->action(fn (Entity $record) => $record->update(['accepts_reports' => true])),
        ];
    }

    public static function getRelations(): array
    {
        return [EntityReports::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageEntidades::route('/'), 'view' => ViewEntity::route('/{record}')];
    }
}
