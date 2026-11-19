<x-filament-panels::page>
    <div class="pd-dashboard">
        @if ($workCards)
            <section class="pd-work-queue" aria-labelledby="work-queue-title">
                <h2 id="work-queue-title">Fila de trabalho</h2>
                <div class="pd-counts">
                    @foreach ($workCards as $card)
                        <a @class(['pd-count', 'pd-count-overdue' => $card['colour'] === 'danger' && $card['total'] > 0]) href="{{ $card['url'] }}">
                            <span><i aria-hidden="true" class="pd-dot pd-{{ $card['colour'] }}"></i>{{ $card['label'] }}</span>
                            <strong>{{ number_format($card['total'], 0, ',', '.') }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
        <div class="pd-period">
            <label>Período de recepção
                <select wire:model.live="period">
                    @foreach ($periods as $key => $label)
                        <option value="{{ $key }}" @selected((string) $key === $period)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if ($period === 'custom')
                <label>De <input type="date" wire:model.change="from"></label>
                <label>Até <input type="date" wire:model.change="until" min="{{ $from }}"></label>
            @endif
        </div>
        @if ($rangeError)
            <p role="status">{{ $rangeError }}</p>
        @else
        <div class="pd-counts" style="--pd-count-columns: {{ count($cards) }}">
            @foreach ($cards as $card)
                <a class="pd-count" href="{{ $card['url'] }}">
                    <span><i aria-hidden="true" class="pd-dot pd-{{ $card['colour'] }}"></i>{{ $card['label'] }}</span>
                    <strong>{{ number_format($card['total'], 0, ',', '.') }}</strong>
                </a>
            @endforeach
        </div>
        @if ($pendingReassignments)
            <x-filament::button tag="a" color="gray" :href="\App\Filament\ReportResource::getUrl('index', ['filters' => ['pending_reassignment' => ['isActive' => 1]]])">
                Pedidos de reencaminhamento pendentes ({{ $pendingReassignments }})
            </x-filament::button>
        @endif
        <section class="pd-section">
            <h2>Denúncias recebidas</h2>
            <p>{{ $rangeLabel }}</p>
            @if ($receivedTotal)
                <div wire:key="series-{{ $period }}-{{ md5(json_encode($chart)) }}"
                    x-data="{ points: @js($chart), active: {{ $peakIndex }},
                        pick(event) {
                            const box = event.currentTarget.getBoundingClientRect();
                            const fraction = ((event.clientX - box.left) / box.width - .005) / .99;
                            this.active = Math.max(0, Math.min(this.points.length - 1, Math.round(fraction * (this.points.length - 1))));
                        } }">
                    <div class="pd-chart-summary">
                        <span><strong>{{ number_format($receivedTotal, 0, ',', '.') }}</strong> no período</span>
                        <span class="pd-reading"><span x-text="points[active].label">{{ $chart[$peakIndex]['label'] }}</span>
                            · <b x-text="points[active].total">{{ $chart[$peakIndex]['total'] }}</b></span>
                    </div>
                    <div class="pd-chart-grid">
                        <div class="pd-scale" aria-hidden="true">
                            @foreach (array_reverse($ticks) as $tick)<span>{{ $tick }}</span>@endforeach
                        </div>
                        <div class="pd-chart-input" tabindex="0" role="slider" aria-label="Explorar denúncias recebidas por período"
                            aria-valuemin="1" aria-valuemax="{{ count($chart) }}" :aria-valuenow="active + 1"
                            :aria-valuetext="points[active].label + ', denúncias recebidas: ' + points[active].total"
                            @pointermove="pick($event)" @click="pick($event)"
                            @keydown.right.prevent="active = Math.min(points.length - 1, active + 1)"
                            @keydown.left.prevent="active = Math.max(0, active - 1)"
                            @keydown.home.prevent="active = 0" @keydown.end.prevent="active = points.length - 1">
                            <svg class="pd-plot" viewBox="0 0 1000 160" preserveAspectRatio="none" aria-hidden="true">
                                <defs><linearGradient id="pd-fill" x2="0" y2="1" gradientUnits="objectBoundingBox"><stop stop-color="currentColor" stop-opacity=".23"/><stop offset="1" stop-color="currentColor" stop-opacity=".02"/></linearGradient></defs>
                                @foreach ($ticks as $tick)
                                    <line class="pd-gridline" x1="0" x2="1000" y1="{{ 155 - 150 * $tick / $chartMax }}" y2="{{ 155 - 150 * $tick / $chartMax }}"/>
                                @endforeach
                                <polygon fill="url(#pd-fill)" points="{{ $chart[0]['x'] }},155 @foreach ($chart as $point){{ $point['x'] }},{{ $point['y'] }} @endforeach {{ last($chart)['x'] }},155"/>
                                <polyline points="@foreach ($chart as $point){{ $point['x'] }},{{ $point['y'] }} @endforeach"/>
                                <line class="pd-guide" :x1="points[active].x" :x2="points[active].x" y1="5" y2="155"/>
                            </svg>
                            <i class="pd-marker" aria-hidden="true" :style="{left: points[active].x / 10 + '%', top: points[active].y / 1.6 + '%'}"></i>
                        </div>
                        <div class="pd-axis">
                            @foreach ($chart as $point)
                                @if ($loop->first || $loop->last || ($loop->count > 4 && $loop->index === intdiv($loop->count - 1, 2)))
                                    <span style="left: {{ $point['x'] / 10 }}%">{{ explode(' – ', $point['label'])[0] }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <details class="pd-series"><summary>Ver valores do gráfico</summary>
                    <table><thead><tr><th>Período</th><th>Denúncias recebidas</th></tr></thead>
                    <tbody>@foreach ($chart as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['total'] }}</td></tr>@endforeach</tbody></table>
                </details>
            @else
                <p>Sem denúncias recebidas neste período.</p>
            @endif
        </section>
        <div @class(['pd-columns' => $admin])>
            <section class="pd-section">
                <h2>{{ $admin ? 'Fila de triagem' : 'Denúncias em aberto' }}</h2>
                @if ($queue->isNotEmpty())
                    <p>As denúncias mais antigas aparecem primeiro.</p>
                    <ul>
                        @foreach ($queue as $report)
                            <li><a class="pd-row" href="{{ \App\Filament\ReportResource::getUrl('view', ['record' => $report]) }}">
                                <span><strong>{{ $report->reference }}</strong><small>{{ $report->category ?? 'Por classificar' }} · {{ $report->municipality ?? 'Município por indicar' }}@unless ($admin) · {{ $report->status }}@endunless</small></span>
                                <time>{{ $report->created_at->timezone('Africa/Luanda')->format('d/m/Y H:i') }}</time>
                            </a></li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ $admin ? 'Não há denúncias a aguardar triagem.' : 'Não há denúncias em aberto neste período.' }}</p>
                @endif
                @if ($admin)
                    <x-filament::button tag="a" :href="\App\Filament\TriageResource::getUrl('index', ['filters' => $dates])">Abrir fila de triagem</x-filament::button>
                @else
                    <x-filament::button tag="a" :href="\App\Filament\ReportResource::getUrl('index', ['filters' => $dates])">Ver denúncias da entidade</x-filament::button>
                @endif
            </section>
            @if ($admin)
            <section class="pd-section">
                <h2>Em tratamento por entidade</h2>
                @if ($workload->isNotEmpty())
                    <p>Até cinco entidades com mais denúncias em tratamento.</p>
                    <ul>
                        @foreach ($workload as $item)
                            <li><a class="pd-work" href="{{ \App\Filament\ReportResource::getUrl('index', ['filters' => $dates + ['status' => ['value' => 'Em tratamento'], 'entity_id' => ['value' => $item->entity_id]]]) }}">
                                <span>{{ $item->entity->name }} <strong>{{ $item->total }}</strong></span>
                                <span aria-hidden="true" class="pd-track"><i style="width: {{ 100 * $item->total / $workload->first()->total }}%"></i></span>
                            </a></li>
                        @endforeach
                    </ul>
                @else
                    <p>Nenhuma entidade tem denúncias em tratamento.</p>
                @endif
            </section>
            @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>
