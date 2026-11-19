<div class="report-detail">
    <section class="rd-description" aria-labelledby="description-title">
        <h2 id="description-title">Descrição da denúncia</h2>
        <p class="rd-text">{{ $report->description }}</p>
    </section>
    @if (filled($report->evidence_description) || $report->evidence_files)
        <section class="rd-description">
            <h2>Provas apresentadas</h2>
            @if (filled($report->evidence_description))
                <p class="rd-text">{{ $report->evidence_description }}</p>
            @endif
            @foreach ($report->evidence_files ?? [] as $index => $path)
                @php
                    $preview = filament()->getCurrentPanel()->route('evidence.preview', ['report' => $report->getKey(), 'index' => $index]);
                    $pdf = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
                @endphp
                <div class="rd-evidence">
                    <strong>Prova {{ $index + 1 }} · {{ strtoupper(pathinfo($path, PATHINFO_EXTENSION)) }}</strong>
                    <x-filament::modal id="prova-{{ $report->getKey() }}-{{ $index }}" width="5xl" heading="Prova {{ $index + 1 }}" teleport="body">
                        <x-slot name="trigger">
                            <x-filament::button icon="heroicon-o-eye" aria-label="Ver prova {{ $index + 1 }}">Ver prova</x-filament::button>
                        </x-slot>
                        <template x-if="isOpen">
                            @if ($pdf)
                                <iframe class="rd-proof-pdf" src="{{ $preview }}" title="Prova {{ $index + 1 }} em PDF"></iframe>
                            @else
                                <img class="rd-proof-image" src="{{ $preview }}" alt="Imagem anexada como prova {{ $index + 1 }}">
                            @endif
                        </template>
                    </x-filament::modal>
                    <x-filament::button color="gray" wire:click="downloadEvidence({{ $index }})" aria-label="Descarregar prova {{ $index + 1 }}">
                        Descarregar
                    </x-filament::button>
                </div>
            @endforeach
        </section>
    @endif
    @if ($administrative && ($classification = $events->first(fn ($event) => $event->false_report_reason !== null)))
        <section class="rd-classification">
            <h2>Motivo da classificação</h2>
            <p class="rd-text">{{ $classification->false_report_reason }}</p>
        </section>
    @endif
    <div class="rd-context">
        <section class="rd-occurrence">
            <h2>Detalhes da ocorrência</h2>
            <dl class="rd-fields">
                <div><dt>Categoria</dt><dd>{{ $report->category ?? 'Por classificar' }}</dd></div>
                <div><dt>Município</dt><dd>{{ $report->municipality ?? 'Não indicado' }}</dd></div>
                <div><dt>Local</dt><dd>{{ $report->location }}</dd></div>
                @if ($report->occurred_on)
                    <div><dt>Data da ocorrência</dt><dd>{{ \Illuminate\Support\Carbon::parse($report->occurred_on)->format('d/m/Y') }}</dd></div>
                @endif
            </dl>
        </section>
        <section>
            <h2>Tratamento da denúncia</h2>
            <dl>
                <div><dt>Entidade responsável</dt><dd>{{ $report->entity?->name ?? 'Por atribuir' }}</dd></div>
                <div><dt>Operador atribuído</dt><dd>{{ $report->operator?->name ?? 'Por atribuir' }}</dd></div>
                @if (!$administrative && in_array($report->status, \App\Models\Report::ACTIVE, true))
                    <div>
                        <dt>Próxima acção</dt>
                        <dd>
                            @if ($report->next_action_on)
                                <time datetime="{{ $report->next_action_on->toDateString() }}">{{ $report->next_action_on->format('d/m/Y') }}</time>
                                @if ($report->hasOverdueNextAction())
                                    <x-filament::badge color="danger" class="inline-flex">Prazo vencido</x-filament::badge>
                                @endif
                            @else
                                Por definir
                            @endif
                        </dd>
                    </div>
                @endif
                <div><dt>Recepção (hora de Luanda)</dt><dd>{{ $report->created_at->timezone('Africa/Luanda')->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </section>
        <section>
            <h2>Denunciante</h2>
            <dl>
                <div><dt>{{ $report->name ? 'Nome' : 'Identificação' }}</dt><dd>{{ $report->name ?? 'Anónima' }}</dd></div>
                @if ($report->phone)
                    <div><dt>Telefone de contacto</dt><dd><a href="tel:{{ $report->phone }}">{{ $report->phone }}</a></dd></div>
                @endif
            </dl>
        </section>
    </div>

    @if ($report->final_verdict !== null)
        <section class="rd-description">
            <h2>Veredito final</h2>
            <p class="rd-text">{{ $report->final_verdict }}</p>
        </section>
    @endif

    <section class="rd-history" aria-labelledby="messages-title">
        <div class="rd-history-title">
            <h2 id="messages-title">Mensagens da denúncia</h2>
            <small>Hora de Luanda</small>
        </div>
        @if ($messages->isEmpty())
            <p>Ainda não há mensagens nesta denúncia.</p>
        @else
            <ol>
                @foreach ($messages as $message)
                    <li>
                        <strong>{{ $message->senderLabel() }}</strong>
                        @if ($message->sender_entity_name)
                            <small>· {{ $message->sender_entity_name }}</small>
                        @endif
                        <time datetime="{{ $message->created_at->toISOString() }}">{{ $message->created_at->timezone('Africa/Luanda')->format('d/m/Y H:i') }}</time>
                        <p class="rd-text">{{ $message->message }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section class="rd-history" aria-labelledby="internal-notes-title">
        <div class="rd-history-title">
            <h2 id="internal-notes-title">Notas internas</h2>
            <small>Hora de Luanda</small>
        </div>
        @if ($internalNotes->isEmpty())
            <p>Ainda não há notas internas nesta denúncia.</p>
        @else
            <ol>
                @foreach ($internalNotes as $note)
                    <li>
                        <strong>{{ $note->author_name }}</strong>
                        <time datetime="{{ $note->created_at->toISOString() }}">{{ $note->created_at->timezone('Africa/Luanda')->format('d/m/Y H:i') }}</time>
                        <p class="rd-text">{{ $note->body }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    @if ($reassignmentRequests->isNotEmpty())
        <section class="rd-history">
            <h2>Pedidos de reencaminhamento</h2>
            <ol>
                @foreach ($reassignmentRequests as $request)
                    <li>
                        <time>{{ $request->created_at->timezone('Africa/Luanda')->format('d/m/Y H:i') }}</time>
                        <p><strong>{{ $request->resolution ?? 'A aguardar decisão' }}</strong> · {{ $request->entity->name }}</p>
                        @if ($request->suggestedEntity)
                            <p>Entidade sugerida: {{ $request->suggestedEntity->name }}</p>
                        @endif
                        <p class="rd-text">Motivo do pedido: {{ $request->reason }}</p>
                        @if ($request->response)
                            <p class="rd-text">Resposta da Administração: {{ $request->response }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
    <section class="rd-history">
        <div class="rd-history-title">
            <h2>{{ $administrative ? 'Histórico administrativo' : 'Histórico público' }}</h2>
            <small>Hora de Luanda</small>
        </div>
        <ol>
            @foreach ($events as $event)
                <li>
                    <time>{{ \Illuminate\Support\Carbon::parse($event->created_at, 'UTC')->setTimezone('Africa/Luanda')->format('d/m/Y H:i') }}</time>
                    <p class="rd-text">{{ $event->message }}</p>
                    @if ($administrative && $event->false_report_reason !== null)
                        <strong>Denúncia Falsa</strong>
                        <small> · Classificada por {{ $event->actor_name }}</small>
                        <p class="rd-note rd-text">Motivo: {{ $event->false_report_reason }}</p>
                    @elseif ($administrative && $event->archive_reason !== null)
                        <small>Arquivada por {{ $event->actor_name }}</small>
                        <p class="rd-note rd-text">Motivo: {{ $event->archive_reason }}</p>
                    @elseif ($administrative && $event->reassignment_reason !== null)
                        <small>Reencaminhamento por {{ $event->actor_name }}</small>
                        <div class="rd-note">
                            <p>Entidade anterior: {{ $event->previous_entity_name }}</p>
                            <p class="rd-text">Motivo: {{ $event->reassignment_reason }}</p>
                        </div>
                    @elseif ($administrative && $event->actor_role === 'admin')
                        <small>Triagem por {{ $event->actor_name }}</small>
                    @elseif ($administrative && $event->actor_role === 'entity')
                        <small>Publicado por {{ $event->actor_name }}</small>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
