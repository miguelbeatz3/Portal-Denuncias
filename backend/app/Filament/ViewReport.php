<?php
namespace App\Filament;

use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public function getTitle(): string
    {
        return 'Denúncia '.$this->getRecord()->reference;
    }

    public function getHeading(): Htmlable
    {
        return new ReportHeading($this->getRecord());
    }

    public function getBreadcrumb(): string
    {
        return $this->getRecord()->reference;
    }

    public function downloadEvidence(int $index)
    {
        $report = ReportResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());
        $path = $report->evidence_files[$index] ?? null;
        abort_unless($path, 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $path, 'Prova-'.($index + 1).'.'.pathinfo($path, PATHINFO_EXTENSION)
        );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Html::make(fn () => ReportResource::details($this->getRecord()))->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = ReportResource::detailActions();
        foreach ($actions as $action) {
            $action->successRedirectUrl(fn () => ReportResource::getUrl('view', ['record' => $this->getRecord()]));
        }
        return $actions;
    }
}
