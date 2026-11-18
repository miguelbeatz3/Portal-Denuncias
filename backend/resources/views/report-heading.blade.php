<span class="rd-heading">
    <span>Denúncia {{ $report->reference }}</span>
    <x-filament::badge class="rd-status" :color="\App\Filament\ReportResource::badgeColor($report->status)"
        :icon="match ($report->status) {
            'Recebida' => 'heroicon-o-inbox',
            'Encaminhada' => 'heroicon-o-paper-airplane',
            'Reencaminhada' => 'heroicon-o-arrows-right-left',
            'Em tratamento' => 'heroicon-o-clock',
            'Concluída' => 'heroicon-o-check-circle',
            'Arquivada' => 'heroicon-o-archive-box',
            default => null,
        }">{{ $report->status }}</x-filament::badge>
    @if (auth()->user()?->role === 'admin' && $report->classified_as_false)
        <x-filament::badge color="danger">Denúncia Falsa</x-filament::badge>
    @endif
</span>
