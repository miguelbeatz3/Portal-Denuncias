<?php
namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser
{
    protected $hidden = ['password', 'remember_token'];

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->role === 'admin' && $this->suspended_at === null,
            'entidade' => $this->role === 'entity' && $this->entity_id !== null && $this->suspended_at === null,
            default => false,
        };
    }
}
