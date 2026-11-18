<?php
namespace App\Providers;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class ResponsiveTables extends ServiceProvider
{
    public function boot(): void
    {
        // Shared by both panels, including tables inside entity and operator records.
        Table::configureUsing(fn (Table $table) => $table->stackedOnMobile());
        TextColumn::configureUsing(fn (TextColumn $column) => $column->wrapHeader());
    }
}
