<?php
namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

class CheckPanelSession extends Authenticate
{
    public function handle($request, Closure $next, ...$guards)
    {
        $guard = Filament::auth();
        $user = $guard->user();
        $key = $user?->role === 'entity' ? 'operator_session_version' : 'admin_session_version';
        if ($user && ($user->suspended_at !== null ||
            (int) $request->session()->get($key, 0) !== (int) $user->session_version)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->to(Filament::getLoginUrl());
        }

        return parent::handle($request, $next, ...$guards);
    }
}
