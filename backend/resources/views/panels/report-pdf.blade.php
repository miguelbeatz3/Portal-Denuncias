<!doctype html>
<html lang="pt-AO"><head><meta charset="utf-8"><title>Relatório de denúncias</title>
<style>
@page { margin: 36pt 38pt; }
body { font: 10pt "DejaVu Sans", sans-serif; color: #01244d; }
.logo { width: 156pt; height: 46pt; }
h1 { font-size: 19pt; margin: 18pt 0 4pt; }
.period { margin: 0 0 14pt; color: #435974; }
.filters { padding: 9pt 12pt; background: #edf6f6; border-left: 3pt solid #018286; line-height: 1.55; overflow-wrap: break-word; }
h2 { font-size: 13pt; margin: 20pt 0 8pt; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8pt; }
thead { display: table-header-group; }
th, td { padding: 6pt 4pt; text-align: right; border-bottom: .6pt solid #cbd8e1; overflow-wrap: break-word; }
th:first-child, td:first-child { width: 30%; text-align: left; }
thead th { background: #01244d; color: white; }
.total td { font-weight: bold; background: #edf6f6; }
</style></head><body>
<img class="logo" src="{{ $logo }}" alt="Portal de Denúncias">
<h1>Relatório de denúncias</h1>
<p class="period">Recebidas de {{ \Carbon\CarbonImmutable::parse($filters['from'])->format('d/m/Y') }} a {{ \Carbon\CarbonImmutable::parse($filters['until'])->format('d/m/Y') }}</p>
<div class="filters">
    <strong>Município</strong> · {{ $filters['municipality'] ?: 'Todos' }}<br>
    <strong>Categoria</strong> · {{ $filters['category'] ?: 'Todas' }}<br>
    <strong>Entidade</strong> · {{ $entityName }}
</div>
<h2>Denúncias por {{ mb_strtolower($dimension) }}</h2>
@if ($rows)
<table><thead><tr><th>{{ $dimension }}</th>@foreach ($states as $label)<th>{{ $label }}</th>@endforeach@if ($administrative)<th>Denúncias falsas</th>@endif <th>Total</th></tr></thead>
<tbody>@foreach ($rows as $row)<tr><td>{{ $row['label'] }}</td>@foreach ($states as $state => $label)<td>{{ $row['states'][$state] }}</td>@endforeach@if ($administrative)<td>{{ $row['false_count'] }}</td>@endif <td>{{ $row['total'] }}</td></tr>@endforeach
<tr class="total"><td>Total</td>@foreach ($totals as $total)<td>{{ $total }}</td>@endforeach@if ($administrative)<td>{{ $falseTotal }}</td>@endif <td>{{ array_sum($totals) }}</td></tr></tbody></table>@if ($administrative)<p>As denúncias falsas estão incluídas nas arquivadas.</p>@endif 
@else
<p>Nenhuma denúncia corresponde aos filtros seleccionados.</p>
@endif
</body></html>
