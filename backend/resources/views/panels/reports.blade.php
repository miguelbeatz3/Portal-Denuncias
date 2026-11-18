<x-filament-panels::page>
    <div class="pd-dashboard">
        <section class="pd-section">
            <h2>Relatório de denúncias</h2>
            <p>Seleccione o período de recepção e como pretende agrupar os resultados.</p>
            <form class="pd-period" method="get" action="{{ \App\Filament\AdminReports::getUrl() }}">
                <label>De <input type="date" required name="from" value="{{ $filters['from'] }}"></label>
                <label>Até <input type="date" required name="until" value="{{ $filters['until'] }}"></label>
                <label>Agrupar por <select name="group">
                    @foreach ($groups as $key => $label)<option value="{{ $key }}" @selected($key === $filters['group'])>{{ $label }}</option>@endforeach
                </select></label>
                <label>Município <select name="municipality"><option value="">Todos</option>
                    @foreach ($municipalities as $name)<option @selected($name === $filters['municipality'])>{{ $name }}</option>@endforeach
                </select></label>
                <label>Categoria <select name="category"><option value="">Todas</option>
                    @foreach ($categories as $name)<option @selected($name === $filters['category'])>{{ $name }}</option>@endforeach
                </select></label>
                @if ($administrative) <label>Entidade <select name="entity"><option value="">Todas</option>
                    @foreach ($entities as $id => $name)<option value="{{ $id }}" @selected((string) $id === (string) $filters['entity'])>{{ $name }}</option>@endforeach
                </select></label> @endif
                <x-filament::button type="submit">Gerar relatório</x-filament::button>
            </form>
        </section>
        @if ($error)
            <p role="alert">{{ $error }}</p>
        @else
            <section class="pd-section">
                <div class="pd-report-heading">
                    <div><h2>Denúncias por {{ mb_strtolower($groups[$filters['group']]) }}</h2>
                        <p>{{ \Carbon\CarbonImmutable::parse($filters['from'])->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($filters['until'])->format('d/m/Y') }} · Estado actual das denúncias recebidas</p></div>
                    <div class="pd-exports">
                        <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" :href="filament()->getCurrentPanel()->route('reports.export', $filters)">Exportar CSV</x-filament::button>
                        <x-filament::button tag="a" icon="heroicon-o-document-arrow-down" :href="filament()->getCurrentPanel()->route('reports.pdf', $filters)">Exportar PDF</x-filament::button>
                    </div>
                </div>
                @if ($rows)
                    <div class="pd-report-scroll" tabindex="0" role="region" aria-label="Resultados do relatório de denúncias"><table class="pd-report-table">
                        <thead><tr><th scope="col">{{ $groups[$filters['group']] }}</th>
                            @foreach ($states as $label)<th scope="col">{{ $label }}</th>@endforeach@if ($administrative)<th scope="col">Denúncias falsas</th>@endif <th scope="col">Total</th></tr></thead>
                        <tbody>@foreach ($rows as $row)<tr><th scope="row">{{ $row['label'] }}</th>
                            @foreach ($states as $state => $label)<td>{{ $row['states'][$state] }}</td>@endforeach@if ($administrative)<td>{{ $row['false_count'] }}</td>@endif <td>{{ $row['total'] }}</td></tr>@endforeach</tbody>
                        <tfoot><tr><th scope="row">Total</th>@foreach ($states as $state => $label)<td>{{ $totals[$state] }}</td>@endforeach@if ($administrative)<td>{{ $falseTotal }}</td>@endif <td>{{ array_sum($totals) }}</td></tr></tfoot>
                    </table></div>@if ($administrative)<p>As denúncias falsas estão incluídas nas arquivadas.</p>@endif 
                @else
                    <p>Nenhuma denúncia corresponde aos filtros seleccionados.</p>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
