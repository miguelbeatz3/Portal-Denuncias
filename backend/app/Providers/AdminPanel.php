<?php
namespace App\Providers;

use App\Filament\{CategoryResource, EntityResource, MunicipalityResource, OperatorResource, ReportResource, TriageResource};
use App\Filament\AdministratorResource;
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

class AdminPanel extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = config('portal.admin_domain');

        return $panel->default()->id('admin')->domain($domain)->path($domain ? '' : 'admin')->login()
            ->brandName('Portal de Denúncias · Administração')
            ->brandLogo(fn () => view('panels.brand', ['area' => 'Administração']))
            ->brandLogoHeight('auto')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors(['primary' => Color::hex('#018286')])
            ->maxContentWidth(Width::Full)
            ->sidebarWidth('17rem')
            ->sidebarFullyCollapsibleOnDesktop()
            ->pages([\App\Filament\AdminDashboard::class, \App\Filament\AdminReports::class])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.sidebar-styles'))
            ->resources([TriageResource::class, ReportResource::class, EntityResource::class, OperatorResource::class,
                AdministratorResource::class, CategoryResource::class, MunicipalityResource::class])
            ->navigationGroups(['Denúncias', 'Entidades e acessos', 'Dados do formulário', 'Acessos administrativos'])
            ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authenticatedRoutes(fn () => require base_path('routes/evidence.php'))
            ->authenticatedRoutes(fn () => require base_path('routes/reports.php'))
            ->authMiddleware([CheckPanelSession::class], isPersistent: true);
    }

    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user->role === 'admin') {
                session()->put('admin_session_version', (int) $event->user->session_version);
            }
        });
    }
}
