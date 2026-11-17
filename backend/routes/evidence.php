<?php
use App\Filament\ReportResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('denuncias/{report}/provas/{index}', function (int $report, int $index) {
    $record = ReportResource::getEloquentQuery()->findOrFail($report);
    abort_unless(ReportResource::canView($record), 403);
    $path = $record->evidence_files[$index] ?? null;
    $disk = Storage::disk('local');
    abort_unless($path && $disk->exists($path), 404);
    $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        default => abort(415),
    };
    return $disk->response($path, 'Prova-'.($index + 1).'.'.pathinfo($path, PATHINFO_EXTENSION), [
        'Content-Type' => $type,
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ], 'inline');
})->whereNumber(['report', 'index'])->name('evidence.preview');
