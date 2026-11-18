<?php
namespace App\Filament;

use App\Models\Operator;
use Filament\Actions\{Action, CreateAction, EditAction};
use Filament\Forms\Components\{TextInput, Select};
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperatorResource extends Resource
{
    protected static ?string $model = Operator::class;
    protected static ?string $modelLabel = 'Operador';
    protected static ?string $pluralModelLabel = 'Operadores';
    protected static ?string $slug = 'operadores';
    protected static string | \UnitEnum | null $navigationGroup = 'Entidades e acessos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)->unique(table: 'users', ignoreRecord: true),
            TextInput::make('phone')->label('Telefone de contacto (opcional)')->placeholder('Ex.: +244 9XX XXX XXX')
                ->tel()->autocomplete('tel')->maxLength(32),
            Select::make('entity_id')->label('Entidade')->relationship('entity', 'name')->searchable()->preload()
                ->placeholder('Seleccione a entidade')->searchPrompt('Escreva o nome da entidade')
                ->required()->exists('entities', 'id'),
            TextInput::make('password')->label('Palavra-passe')->password()->autocomplete('new-password')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(12)->maxLength(72)->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Na edição, deixe em branco para conservar a palavra-passe.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nome')->searchable()->sortable()->wrap(),
            TextColumn::make('email')->label('E-mail')->searchable()->wrap(),
            TextColumn::make('phone')->label('Telefone')->placeholder('Por indicar'),
            TextColumn::make('entity.name')->label('Entidade')->sortable()->wrap(),
            TextColumn::make('estado')->label('Estado')->badge()
                ->state(fn (Operator $record) => $record->suspended_at === null ? 'Activo' : 'Suspenso')
                ->color(fn (Operator $record) => $record->suspended_at === null ? 'success' : 'danger'),
        ])->headerActions([CreateAction::make()->label('Criar operador')])
            ->recordActions([
                Action::make('consultar')->label('Consultar')->icon(Heroicon::OutlinedEye)->iconButton()->tooltip('Consultar')
                    ->url(fn (Operator $record) => static::getUrl('view', ['record' => $record])),
                ...array_map(fn (Action $action) => $action->iconButton()->tooltip($action->getLabel()), static::managementActions()),
            ]);
    }

    public static function managementActions(): array
    {
        return [
            EditAction::make()->label('Editar')->icon(Heroicon::OutlinedPencilSquare)->databaseTransaction(),
            Action::make('suspender')->label('Suspender')->color('danger')->requiresConfirmation()
                ->icon(Heroicon::OutlinedNoSymbol)
                ->modalDescription('O operador perderá o acesso à área da entidade, incluindo nas sessões abertas.')
                ->visible(fn (Operator $record) => $record->suspended_at === null)
                ->action(fn (Operator $record) => $record->suspend(auth()->user())),
            Action::make('reactivar')->label('Reactivar')->requiresConfirmation()->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (Operator $record) => $record->suspended_at !== null)
                ->action(fn (Operator $record) => $record->reactivate(auth()->user())),
        ];
    }

    public static function getRelations(): array
    {
        return [OperatorReports::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageOperadores::route('/'), 'view' => ViewOperator::route('/{record}')];
    }
}
