<?php
namespace App\Providers;

use App\Filament\ReportResource;
use App\Http\Middleware\CheckPanelSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\{DisableBladeIconComponents, DispatchServingFilamentEvent};
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;

class EntityPanel extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = config('portal.entity_domain');

        return $panel->id('entidade')->domain($domain)->path($domain ? '' : 'entidade')->login()
            ->brandName('Portal de Denúncias · Área da entidade')
            ->brandLogo(fn () => view('panels.brand', ['area' => 'Área da entidade']))
            ->brandLogoHeight('auto')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors(['primary' => Color::hex('#018286')])
            ->maxContentWidth(Width::Full)
            ->sidebarWidth('17rem')
            ->sidebarFullyCollapsibleOnDesktop()
            ->pages([\App\Filament\AdminDashboard::class, \App\Filament\AdminReports::class])
            ->navigationGroups(['Denúncias'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.sidebar-styles'))
            ->resources([ReportResource::class])
            ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authenticatedRoutes(fn () => require base_path('routes/evidence.php'))
            ->authenticatedRoutes(fn () => require base_path('routes/reports.php'))
            ->authMiddleware([CheckPanelSession::class], isPersistent: true);
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user->role === 'entity') {
                session()->put('operator_session_version', (int) $event->user->session_version);
            }
        });
    }
}
