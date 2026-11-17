<?php
use App\Services\Reporting;
use App\Models\Entity;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('relatorios/exportar', function (Request $request) {
    $result = Reporting::generate($request);
    abort_if($result['error'], 422, $result['error'] ?? '');
    $filters = $result['filters'];
    return response()->streamDownload(function () use ($result, $filters) {
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        $write = fn ($row) => fputcsv($stream, $row, ';', '"', '');
        $admin = $result['administrative'];
        $write([Reporting::GROUPS[$filters['group']], ...array_values(Reporting::STATES), ...($admin ? ['Denúncias falsas (das arquivadas)'] : []), 'Total']);
        foreach ($result['rows'] as $row) {
            $label = $row['label'];
            if (preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $label)) $label = "'".$label;
            $write([$label, ...array_values($row['states']), ...($admin ? [$row['false_count']] : []), $row['total']]);
        }
        $write(['Total', ...array_values($result['totals']), ...($admin ? [$result['falseTotal']] : []), array_sum($result['totals'])]);
        fclose($stream);
    }, "denuncias-{$filters['group']}-{$filters['from']}-{$filters['until']}.csv", [
        'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store',
    ]);
})->name('reports.export');

Route::get('relatorios/exportar/pdf', function (Request $request) {
    $result = Reporting::generate($request);
    abort_if($result['error'], 422, $result['error'] ?? '');
    $filters = $result['filters'];
    $entityName = filled($filters['entity'])
        ? Entity::whereKey($filters['entity'])->value('name') ?? 'Entidade indisponível'
        : 'Todas';
    $pdf = new Dompdf();
    $pdf->setPaper('A4', 'landscape');
    $pdf->loadHtml(view('panels.report-pdf', $result + [
        'dimension' => Reporting::GROUPS[$filters['group']],
        'entityName' => $entityName,
        'states' => Reporting::STATES,
        'logo' => 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('brand/portal-denuncias.svg'))),
    ])->render(), 'UTF-8');
    $pdf->render();
    return response($pdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="denuncias-'.$filters['group'].'-'.$filters['from'].'-'.$filters['until'].'.pdf"',
        'Cache-Control' => 'private, no-store',
    ]);
})->name('reports.pdf');
