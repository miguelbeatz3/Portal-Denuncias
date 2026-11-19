<?php
namespace App\Filament;

use App\Models\{Category, Entity, Operator, Report, ReportInternalNote};
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\{DatePicker, Hidden, Select, Textarea};
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
        if (static::class === self::class) {
            $query->addSelect(['classified_as_false' => self::falseClassification()->limit(1)]);
        }
        if ($user->role !== 'admin') {
            $query->where('entity_id', $user->entity_id)->whereNotNull('entity_id');
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
            'Encaminhada', 'Reencaminhada' => 'primary',
            'Em tratamento' => 'info',
            'A aguardar resposta' => 'warning',
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
            TextColumn::make('category')->label('Categoria')->placeholder('Por classificar')->wrap(),
            TextColumn::make('entity.name')->label('Entidade')->placeholder('Por atribuir')
                ->visible(fn () => auth()->user()->role === 'admin' && $showEntity)->wrap(),
            TextColumn::make('municipality')->label('Município')->placeholder('Não indicado')->wrap(),
            TextColumn::make('location')->label('Local')->wrap(),
            TextColumn::make('status')->label('Estado')->badge()
                ->state(fn (Report $record) => match (true) {
                    (bool) $record->classified_as_false => [$record->status, 'Denúncia Falsa'],
                    auth()->user()->role === 'entity' && $record->isAwaitingReply() => [$record->status, 'A aguardar resposta'],
                    default => $record->status,
                })
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
        return $table->extraAttributes(['class' => 'pd-reports-table'])->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => auth()->user()->role === 'entity'
                ? $query->with('operator:id,name')
                    ->withCount(['messages as unread_messages_count' => fn (Builder $messages) => $messages->unreadBy(auth()->user())])
                : $query)
            ->columns([
                ...static::reportColumns(static::class === self::class),
                TextColumn::make('operator.name')->label('Operador')->placeholder('Por atribuir')->wrap()
                    ->visible(fn () => auth()->user()->role === 'entity'),
                TextColumn::make('next_action_on')->label('Próxima acção')->badge()->placeholder('Por definir')
                    ->formatStateUsing(fn (Report $record) => $record->next_action_on?->format('d/m/Y')
                        .($record->hasOverdueNextAction() ? ' · Vencido' : ''))
                    ->color(fn (Report $record) => $record->hasOverdueNextAction() ? 'danger' : 'gray')
                    ->visible(fn () => auth()->user()->role === 'entity'),
                TextColumn::make('unread_messages_count')->label('Por ler')->badge()->color('warning')
                    ->state(fn (Report $record) => $record->unread_messages_count ?: null)->placeholder('—')
                    ->visible(fn () => auth()->user()->role === 'entity'),
            ])->filters([
            self::receivedFilter(),
            Filter::make('classified_as_false')->label('Apenas denúncias falsas')
                ->visible(fn () => auth()->user()->role === 'admin')
                ->query(fn (Builder $query) => $query->whereExists(self::falseClassification())),
            Filter::make('pending_reassignment')->label('Pedidos de reencaminhamento pendentes')
                ->visible(fn () => auth()->user()->role === 'admin')
                ->query(fn (Builder $query) => auth()->user()->role === 'admin' ? $query->whereHas('pendingReassignmentRequest') : $query),
            SelectFilter::make('status')->label('Estado')
                ->options(Report::STATES),
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
            self::distributeOperatorAction()->iconButton()->tooltip(fn (Report $record) => self::operatorActionLabel($record)),
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
            self::reassignAction(detail: true),
            self::declineReassignmentAction(),
            self::distributeOperatorAction()->button(),
            Action::make('agendarProximaAccao')->label('Próxima acção')->icon(Heroicon::OutlinedCalendarDays)->button()->color('gray')
                ->visible(fn (Report $record) => auth()->user()->role === 'entity' && in_array($record->status, Report::ACTIVE, true))
                ->fillForm(fn (Report $record) => ['next_action_on' => $record->next_action_on?->toDateString()])
                ->schema([DatePicker::make('next_action_on')->label('Data da próxima acção')
                    ->helperText('Deixe a data em branco para retirar o prazo.')])
                ->modalSubmitActionLabel('Guardar prazo')->successNotificationTitle('Prazo actualizado')
                ->action(fn (Report $record, array $data) => ReportWorkflow::scheduleNextAction($record, auth()->user(), $data['next_action_on'] ?? null)),
            Action::make('iniciarTratamento')->label('Iniciar tratamento')->icon(Heroicon::OutlinedPlay)->button()
                ->visible(fn (Report $record) => auth()->user()->role === 'entity'
                    && in_array($record->status, Report::AWAITING_TREATMENT, true))
                ->requiresConfirmation()->modalDescription('O cidadão passará a ver o estado «Em tratamento».')
                ->action(fn (Report $record) => ReportWorkflow::startTreatment($record, auth()->user())),
            self::requestReassignmentAction(),
            Action::make('enviarMensagem')->label('Enviar mensagem')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->button()->color('gray')->modalSubmitActionLabel('Enviar mensagem')
                ->modalDescription('A mensagem fica visível ao denunciante e às equipas com acesso à denúncia.')
                ->schema([Textarea::make('message')->label('Mensagem')->required()->maxLength(2000)])
                ->successNotificationTitle('Mensagem enviada')
                ->action(fn (Report $record, array $data) => ReportWorkflow::sendMessage($record, auth()->user(), $data['message'])),
            Action::make('aguardarResposta')->label('Marcar a aguardar resposta')->icon(Heroicon::OutlinedClock)->button()->color('gray')
                ->visible(fn (Report $record) => auth()->user()->role === 'entity'
                    && $record->status === 'Em tratamento' && !$record->isAwaitingReply())
                ->successNotificationTitle('Denúncia a aguardar resposta')
                ->action(fn (Report $record) => ReportWorkflow::setAwaitingReply($record, auth()->user(), true)),
            Action::make('retomarTratamento')->label('Retomar tratamento')->icon(Heroicon::OutlinedPlay)->button()->color('gray')
                ->visible(fn (Report $record) => auth()->user()->role === 'entity' && $record->isAwaitingReply())
                ->successNotificationTitle('Tratamento retomado')
                ->action(fn (Report $record) => ReportWorkflow::setAwaitingReply($record, auth()->user(), false)),
            Action::make('adicionarNotaInterna')->label('Adicionar nota interna')->icon(Heroicon::OutlinedClipboardDocumentList)
                ->button()->color('gray')->modalSubmitActionLabel('Guardar nota')
                ->modalDescription('A nota fica visível à Administração e à equipa da entidade responsável.')
                ->schema([Textarea::make('body')->label('Nota interna')->required()->maxLength(ReportInternalNote::MAX_LENGTH)->rows(5)])
                ->successNotificationTitle('Nota interna guardada')
                ->action(fn (Report $record, array $data) => ReportWorkflow::addInternalNote($record, auth()->user(), $data['body'])),
            Action::make('arquivar')->label('Arquivar')->icon(Heroicon::OutlinedArchiveBox)->button()->color('gray')
                ->visible(fn (Report $record) => ReportWorkflow::canArchive($record, auth()->user()))
                ->modalHeading('Arquivar denúncia')->modalSubmitActionLabel('Confirmar arquivo')
                ->schema([
                    Textarea::make('reason')->label('Motivo do arquivo')->required()->maxLength(2000)
                        ->helperText('O motivo fica visível apenas à Administração.'),
                ])
                ->action(fn (Report $record, array $data) => ReportWorkflow::archive($record, auth()->user(), $data['reason'])),
            Action::make('denunciaFalsa')->label('Denúncia Falsa')->icon(Heroicon::OutlinedFlag)->button()->color('danger')
                ->visible(fn (Report $record) => ReportWorkflow::canArchive($record, auth()->user(), falseReport: true))
                ->modalHeading('Classificar como denúncia falsa')
                ->modalDescription('Esta decisão arquiva a denúncia. O cidadão verá o estado «Arquivada» e a classificação «Denúncia Falsa». O motivo fica no histórico administrativo.')
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required()->maxLength(2000)
                        ->helperText('O motivo fica registado no histórico administrativo.'),
                ])
                ->modalSubmitActionLabel('Confirmar classificação')
                ->action(fn (Report $record, array $data) => ReportWorkflow::classifyAsFalse($record, auth()->user(), $data['reason'])),
            Action::make('concluir')->label('Concluir')->icon(Heroicon::OutlinedCheckCircle)->button()->color('success')
                ->visible(fn (Report $record) => auth()->user()->role === 'entity' && $record->status === 'Em tratamento')
                ->modalHeading('Concluir denúncia')->modalSubmitActionLabel('Confirmar conclusão')
                ->schema([
                    Textarea::make('verdict')->label('Veredito final')->required()->maxLength(2000)
                        ->helperText('O veredito fica visível ao denunciante no acompanhamento.'),
                ])
                ->action(fn (Report $record, array $data) => ReportWorkflow::conclude($record, auth()->user(), $data['verdict'])),
        ];
    }

    private static function operatorActionLabel(Report $report): string
    {
        return $report->operator_id ? 'Reatribuir operador' : 'Atribuir operador';
    }

    private static function distributeOperatorAction(): Action
    {
        $actor = auth()->user();
        $canDistribute = $actor?->canDistributeReports();
        return Action::make('distribuirOperador')->label(fn (Report $record) => self::operatorActionLabel($record))
            ->icon(Heroicon::OutlinedUserPlus)->color('gray')
            ->visible(fn (Report $record) => $canDistribute && (int) $actor->entity_id === (int) $record->entity_id
                && in_array($record->status, Report::ACTIVE, true))
            ->fillForm(fn (Report $record) => ['previous_operator_id' => $record->operator_id])
            ->schema([
                Hidden::make('previous_operator_id'),
                Select::make('operator_id')->label('Operador')->searchable()->required()
                    ->options(fn (Report $record) => Operator::activeInEntity($record->entity_id)
                        ->when($record->operator_id, fn (Builder $query, $operatorId) => $query->whereKeyNot($operatorId))
                        ->orderBy('name')->pluck('name', 'id')),
            ])
            ->successNotificationTitle('Operador atribuído')
            ->action(fn (Report $record, array $data) => ReportWorkflow::distributeOperator($record, auth()->user(),
                (int) $data['operator_id'], isset($data['previous_operator_id']) ? (int) $data['previous_operator_id'] : null));
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

    private static function reassignAction(bool $detail = false): Action
    {
        return Action::make('reencaminhar')
            ->label(fn (Report $record) => $detail && $record->pendingReassignmentRequest ? 'Aprovar e reencaminhar' : 'Reencaminhar')
            ->icon(Heroicon::OutlinedArrowsRightLeft)->button()
            ->visible(fn (Report $record) => auth()->user()->role === 'admin' && in_array($record->status, Report::ACTIVE, true))
            ->fillForm(fn (Report $record) => ['previous_entity_id' => $record->entity_id,
                'request_id' => $record->pendingReassignmentRequest?->id,
                'entity_id' => $record->pendingReassignmentRequest?->suggested_entity_id])
            ->schema([
                Hidden::make('previous_entity_id')->required(),
                Hidden::make('request_id'),
                Select::make('entity_id')->label('Nova entidade')->searchable()->required()
                    ->options(fn (Report $record) => Entity::destinations($record)),
                Textarea::make('reason')->label('Motivo do reencaminhamento')->required()->maxLength(2000)
                    ->helperText('Visível apenas no histórico administrativo.'),
            ])->action(fn (Report $record, array $data) => ReportWorkflow::reassign($record, auth()->user(), $data));
    }

    private static function requestReassignmentAction(): Action
    {
        return Action::make('solicitarReencaminhamento')->label('Solicitar reencaminhamento')
            ->icon(Heroicon::OutlinedArrowsRightLeft)->button()->color('gray')
            ->visible(fn (Report $record) => auth()->user()->role === 'entity' && in_array($record->status, Report::ACTIVE, true)
                && !$record->pendingReassignmentRequest)
            ->modalDescription('A Administração analisa o pedido. A denúncia mantém-se atribuída à sua entidade até à decisão.')
            ->schema([
                Select::make('suggested_entity_id')->label('Entidade sugerida (opcional)')->searchable()
                    ->options(fn (Report $record) => Entity::destinations($record)),
                Textarea::make('reason')->label('Motivo do pedido')->required()->maxLength(2000),
            ])->action(fn (Report $record, array $data) => ReportWorkflow::requestReassignment($record, auth()->user(), $data));
    }

    private static function declineReassignmentAction(): Action
    {
        return Action::make('recusarReencaminhamento')->label('Recusar pedido')->icon(Heroicon::OutlinedXMark)->button()->color('gray')
            ->visible(fn (Report $record) => auth()->user()->role === 'admin' && (bool) $record->pendingReassignmentRequest)
            ->fillForm(fn (Report $record) => ['request_id' => $record->pendingReassignmentRequest?->id])
            ->schema([
                Hidden::make('request_id')->required(),
                Textarea::make('reason')->label('Motivo da recusa')->required()->maxLength(2000)
                    ->helperText('A entidade que fez o pedido poderá ler este motivo.'),
            ])->action(fn (Report $record, array $data) => ReportWorkflow::declineReassignment(
                $record, auth()->user(), (int) $data['request_id'], $data['reason']));
    }

    public static function details(Report $record, bool $markMessagesRead = false): View
    {
        $user = auth()->user();
        $administrative = $user->role === 'admin';
        $record = static::getEloquentQuery()->with(['entity', 'operator'])->findOrFail($record->id);
        $events = DB::table('report_events as event')
            ->where('event.report_id', $record->id)
            ->select('event.message', 'event.created_at')
            ->orderBy('event.id');
        if ($administrative) {
            $events->leftJoin('users as actor', 'actor.id', '=', 'event.user_id')
                ->addSelect('actor.name as actor_name', 'actor.role as actor_role', 'event.previous_entity_name',
                    'event.reassignment_reason', 'event.false_report_reason', 'event.archive_reason');
        }

        $requests = $record->reassignmentRequests()->with(['entity', 'suggestedEntity'])->latest('id')
            ->when(!$administrative, fn (Builder $query) => $query->where('entity_id', $user->entity_id))->get();

        $messages = $record->messages()->orderBy('id')->get();
        if ($markMessagesRead && $user->role === 'entity' && ($lastMessage = $messages->last(fn ($message) => $message->sender === 'citizen'))) {
            ReportWorkflow::markMessagesRead($record, $user, $lastMessage->id);
        }
        $notes = $record->internalNotes()
            ->whereIn('report_id', static::getEloquentQuery()->whereKey($record->id)->select('reports.id'))
            ->latest('id')->get();

        return view('details', ['report' => $record, 'events' => $events->get(), 'administrative' => $administrative,
            'reassignmentRequests' => $requests, 'messages' => $messages, 'internalNotes' => $notes]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReports::route('/'), 'view' => ViewReport::route('/{record}')];
    }
}
