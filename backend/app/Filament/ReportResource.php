<?php
namespace App\Filament;

use App\Models\{Category, Entity, Report};
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\{Hidden, Select, Textarea, Toggle};
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{Filter, SelectFilter};
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;
    protected static ?string $modelLabel = 'Denúncia';
    protected static ?string $pluralModelLabel = 'Denúncias';
    protected static ?string $slug = 'denuncias';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return 'Denúncias';
    }

    public static function getNavigationLabel(): string
    {
        return filament()->getCurrentPanel()?->getId() === 'admin' ? 'Todas as denúncias' : 'Denúncias da entidade';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();
        if ($user->role !== 'admin') {
            return $query->where('entity_id', $user->entity_id)->whereNotNull('entity_id');
        }
        if (static::class === self::class) {
            $query->addSelect(['classified_as_false' => self::falseClassification()->limit(1)]);
        }
        return $query;
    }

    public static function falseClassification(): QueryBuilder
    {
        return DB::table('report_events')->selectRaw('1')
            ->whereColumn('report_events.report_id', 'reports.id')->whereNotNull('false_report_reason');
    }

    public static function badgeColor(string $state): string
    {
        return match ($state) {
            'Recebida' => 'warning',
            'Em tratamento' => 'info',
            'Concluída' => 'success',
            'Denúncia Falsa' => 'danger',
            default => 'gray',
        };
    }

    public static function reportColumns(bool $showEntity): array
    {
        return [
            TextColumn::make('reference')->label('Referência')->searchable(),
            TextColumn::make('created_at')->label('Data de recepção')->dateTime('d/m/Y H:i', 'Africa/Luanda'),
            TextColumn::make('category')->label('Categoria')->placeholder('Por classificar'),
            TextColumn::make('entity.name')->label('Entidade')->placeholder('Por atribuir')
                ->visible(fn () => auth()->user()->role === 'admin' && $showEntity),
            TextColumn::make('municipality')->label('Município')->placeholder('Não indicado')->wrap(),
            TextColumn::make('location')->label('Local')->wrap(),
            TextColumn::make('status')->label('Estado')->badge()
                ->state(fn (Report $record) => $record->classified_as_false
                    ? [$record->status, 'Denúncia Falsa'] : $record->status)
                ->color(fn (string $state) => self::badgeColor($state)),
        ];
    }

    public static function consultAction(): Action
    {
        return Action::make('detalhes')->label('Consultar')->icon(Heroicon::OutlinedEye)->iconButton()->tooltip('Consultar')
            ->url(fn (Report $record) => ReportResource::getUrl('view', ['record' => $record]));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns(static::reportColumns(static::class === self::class))->filters([
            self::receivedFilter(),
            Filter::make('classified_as_false')->label('Apenas denúncias falsas')
                ->visible(fn () => auth()->user()->role === 'admin')
                ->query(fn (Builder $query) => $query->whereExists(self::falseClassification())),
            SelectFilter::make('status')->label('Estado')
                ->options(['Recebida' => 'Por triar', 'Em tratamento' => 'Em tratamento', 'Concluída' => 'Concluída', 'Arquivada' => 'Arquivada']),
            SelectFilter::make('entity_id')->label('Entidade')
                ->options(fn () => Entity::orderBy('name')->pluck('name', 'id'))
                ->visible(fn () => auth()->user()->role === 'admin'),
            SelectFilter::make('category')->label('Categoria')
                ->options(fn () => Category::pluck('name')->merge(static::getEloquentQuery()->whereNotNull('category')->select('category')->distinct()->pluck('category'))
                    ->unique()->sort()->mapWithKeys(fn ($name) => [$name => $name])->all()),
        ])->recordActions([
            self::consultAction(),
            self::assignAction()->iconButton()->tooltip('Encaminhar'),
            self::reassignAction()->iconButton()->tooltip('Reencaminhar'),
            self::publishAction()->iconButton()->tooltip('Publicar actualização'),
        ]);
    }

    protected static function receivedFilter(): Filter
    {
        return Filter::make('received')->label('Data de recepção')
            ->schema([
                \Filament\Forms\Components\DatePicker::make('from')->label('Recebida desde'),
                \Filament\Forms\Components\DatePicker::make('until')->label('Recebida até'),
            ])->query(function (Builder $query, array $data): Builder {
                foreach (['from', 'until'] as $key) {
                    $value = $data[$key] ?? null;
                    if (!is_string($value) || !\Carbon\CarbonImmutable::hasFormat($value, 'Y-m-d')) continue;
                    $date = \Carbon\CarbonImmutable::parse($value, 'Africa/Luanda');
                    if ($key === 'from') $query->where('created_at', '>=', $date->utc());
                    else $query->where('created_at', '<', $date->addDay()->utc());
                }
                return $query;
            });
    }

    public static function detailActions(): array
    {
        return [
            self::assignAction(),
            self::reassignAction(),
            Action::make('arquivar')->label('Arquivar')->icon(Heroicon::OutlinedArchiveBox)->button()->color('gray')
                ->visible(fn (Report $record) => auth()->user()->role === 'admin' && $record->status !== 'Arquivada')
                ->modalHeading('Arquivar denúncia')->modalSubmitActionLabel('Confirmar arquivo')
                ->schema([
                    Textarea::make('reason')->label('Motivo do arquivo')->required()->maxLength(2000)
                        ->helperText('O motivo fica visível apenas à Administração.'),
                ])
                ->action(fn (Report $record, array $data) => ReportWorkflow::archive($record, auth()->user(), $data['reason'])),
            Action::make('denunciaFalsa')->label('Denúncia Falsa')->icon(Heroicon::OutlinedFlag)->button()->color('danger')
                ->visible(fn (Report $record) => auth()->user()->role === 'admin'
                    && in_array($record->status, ['Recebida', 'Em tratamento'], true))
                ->modalHeading('Classificar como denúncia falsa')
                ->modalDescription('Esta decisão arquiva a denúncia. O cidadão verá o estado «Arquivada» e a classificação «Denúncia Falsa». O motivo fica no histórico administrativo.')
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required()->maxLength(2000)
                        ->helperText('O motivo fica registado no histórico administrativo.'),
                ])
                ->modalSubmitActionLabel('Confirmar classificação')
                ->action(fn (Report $record, array $data) => ReportWorkflow::classifyAsFalse($record, auth()->user(), $data['reason'])),
            self::publishAction(),
        ];
    }

    private static function publishAction(): Action
    {
        return Action::make('actualizar')->label('Publicar actualização')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (Report $record) => auth()->user()->role === 'entity' && $record->status === 'Em tratamento')
                ->schema([
                    Textarea::make('message')->label('Mensagem visível ao cidadão')->required()->maxLength(2000),
                    Toggle::make('close')->label('Concluir denúncia'),
                ])->action(fn (Report $record, array $data) => ReportWorkflow::publish($record, auth()->user(), $data));
    }

    private static function assignAction(): Action
    {
        return Action::make('encaminhar')->label('Encaminhar')->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Report $record) => auth()->user()->role === 'admin' && $record->status === 'Recebida')
            ->fillForm(fn (Report $record) => ['category' => $record->category])
            ->schema([
                Select::make('category')->label('Categoria revista')->placeholder('Seleccione a categoria')->required()
                    ->options(fn () => Category::where('active', true)->orderBy('name')->pluck('name', 'name')),
                Select::make('entity_id')->label('Entidade responsável')->searchable()
                    ->options(fn (Report $record) => Entity::destinations($record))->required(),
            ])->action(fn (Report $record, array $data) => ReportWorkflow::assign($record, auth()->user(), $data));
    }

    private static function reassignAction(): Action
    {
        return Action::make('reencaminhar')->label('Reencaminhar')->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (Report $record) => auth()->user()->role === 'admin' && $record->status === 'Em tratamento')
            ->fillForm(fn (Report $record) => ['previous_entity_id' => $record->entity_id])
            ->schema([
                Hidden::make('previous_entity_id')->required(),
                Select::make('entity_id')->label('Nova entidade')->searchable()->required()
                    ->options(fn (Report $record) => Entity::destinations($record)),
                Textarea::make('reason')->label('Motivo da correcção')->required()->maxLength(2000)
                    ->helperText('Visível apenas no histórico administrativo.'),
            ])->action(fn (Report $record, array $data) => ReportWorkflow::reassign($record, auth()->user(), $data));
    }

    public static function details(Report $record): View
    {
        $administrative = auth()->user()->role === 'admin';
        $record = static::getEloquentQuery()->with('entity')->findOrFail($record->id);
        $events = DB::table('report_events as event')
            ->where('event.report_id', $record->id)
            ->select('event.message', 'event.created_at')
            ->orderBy('event.id');
        if ($administrative) {
            $events->leftJoin('users as actor', 'actor.id', '=', 'event.user_id')
                ->addSelect('actor.name as actor_name', 'actor.role as actor_role', 'event.previous_entity_name',
                    'event.reassignment_reason', 'event.false_report_reason', 'event.archive_reason');
        }

        return view('details', ['report' => $record, 'events' => $events->get(), 'administrative' => $administrative]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReports::route('/'), 'view' => ViewReport::route('/{record}')];
    }
}
