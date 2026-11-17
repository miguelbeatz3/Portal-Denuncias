<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Administrator extends User
{
    protected $table = 'users';
    protected $attributes = ['role' => 'admin'];
    protected $fillable = ['name', 'email', 'phone', 'password'];
    protected $casts = ['password' => 'hashed'];

    protected static function booted(): void
    {
        static::addGlobalScope('administrators', fn (Builder $query) => $query->where('role', 'admin'));
    }

    public function setSuspended(bool $suspend, User $actor): void
    {
        abort_if($this->is($actor), 403);
        DB::transaction(function () use ($actor, $suspend) {
            $accounts = static::whereKey([$actor->id, $this->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $admin = $accounts->get($actor->id);
            abort_unless($admin && $admin->suspended_at === null, 403);
            $target = $accounts->get($this->id);
            abort_unless($target, 404);
            if ($suspend === ($target->suspended_at !== null)) return;
            $target->suspended_at = $suspend ? now() : null;
            if ($suspend) {
                $target->session_version++;
                $target->setRememberToken(Str::random(60));
            }
            $target->save();
        });
    }
}
