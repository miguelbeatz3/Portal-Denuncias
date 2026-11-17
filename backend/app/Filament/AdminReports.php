<?php
namespace App\Filament;

use App\Models\{Entity, Report};
use App\Services\Reporting;
use Filament\Pages\Page;

class AdminReports extends Page
{
    protected static ?string $title = 'Relatórios';
    protected static ?string $slug = 'relatorios';
    protected static string | \UnitEnum | null $navigationGroup = 'Denúncias';
    protected static ?int $navigationSort = 2;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected string $view = 'panels.reports';

    protected function getViewData(): array
    {
        $result = Reporting::generate(request());
        $admin = auth()->user()->role === 'admin';
        $reports = Report::query();
        if (!$admin) $reports->where('entity_id', auth()->user()->entity_id);
        return $result + [
            'administrative' => $admin,
            'groups' => Reporting::GROUPS, 'states' => Reporting::STATES,
            'municipalities' => (clone $reports)->whereNotNull('municipality')->distinct()->orderBy('municipality')->pluck('municipality'),
            'categories' => (clone $reports)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'entities' => $admin ? Entity::orderBy('name')->pluck('name', 'id') : [],
        ];
    }
}
