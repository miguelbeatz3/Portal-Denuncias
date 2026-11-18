<?php
namespace App\Filament;

use App\Models\Administrator;
use Filament\Actions\{Action, CreateAction, EditAction};
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdministratorResource extends Resource
{
    protected static ?string $model = Administrator::class;
    protected static ?string $modelLabel = 'Utilizador';
    protected static ?string $pluralModelLabel = 'Utilizadores';
    protected static ?string $slug = 'utilizadores';
    protected static string | \UnitEnum | null $navigationGroup = 'Acessos administrativos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin' && auth()->user()->suspended_at === null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->placeholder('Nome completo')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->placeholder('nome@exemplo.ao')->email()->required()
                ->maxLength(255)->unique(table: 'users', ignoreRecord: true),
            TextInput::make('phone')->label('Telefone (opcional)')->placeholder('+244 9XX XXX XXX')->tel()->maxLength(32),
            TextInput::make('password')->label('Palavra-passe')->password()->autocomplete('new-password')
                ->required(fn (string $operation) => $operation === 'create')->minLength(12)->maxLength(72)
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Utilize pelo menos 12 caracteres. Na edição, deixe em branco para conservar a palavra-passe.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->description('Contas com acesso ao painel de Administração.')->columns([
            TextColumn::make('name')->label('Nome')->searchable()->sortable()->wrap(),
            TextColumn::make('email')->label('E-mail')->searchable()->wrap(),
            TextColumn::make('phone')->label('Telefone')->placeholder('Por indicar'),
            TextColumn::make('estado')->label('Estado')->badge()
                ->state(fn (Administrator $record) => $record->suspended_at === null ? 'Activo' : 'Suspenso')
                ->color(fn (Administrator $record) => $record->suspended_at === null ? 'success' : 'danger'),
        ])->headerActions([CreateAction::make()->label('Criar utilizador')
            ->modalDescription('Esta conta terá acesso administrativo ao portal.')])
            ->recordActions([
                EditAction::make()->label('Editar')->icon(Heroicon::OutlinedPencilSquare)->iconButton()->tooltip('Editar'),
                Action::make('suspender')->label('Suspender')->icon(Heroicon::OutlinedNoSymbol)->iconButton()
                    ->tooltip('Suspender')->color('danger')->requiresConfirmation()
                    ->visible(fn (Administrator $record) => !$record->is(auth()->user()) && $record->suspended_at === null)
                    ->action(fn (Administrator $record) => $record->setSuspended(true, auth()->user())),
                Action::make('reactivar')->label('Reactivar')->icon(Heroicon::OutlinedCheckCircle)->iconButton()
                    ->tooltip('Reactivar')->requiresConfirmation()
                    ->visible(fn (Administrator $record) => $record->suspended_at !== null)
                    ->action(fn (Administrator $record) => $record->setSuspended(false, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUtilizadores::route('/')];
    }
}
