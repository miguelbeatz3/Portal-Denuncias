<?php
namespace App\Filament;

use App\Models\Report;
use Illuminate\Contracts\Support\Htmlable;

// Keep the rendered heading as Htmlable through Blade's modal attribute binding.
final class ReportHeading implements Htmlable
{
    public function __construct(private readonly Report $report) {}

    public function toHtml(): string
    {
        return view('report-heading', ['report' => $this->report])->render();
    }
}
