<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Operator extends User
{
    protected $table = 'users';
    protected $attributes = ['role' => 'entity'];
    protected $fillable = ['name', 'email', 'phone', 'password', 'entity_id'];
    protected $casts = ['password' => 'hashed'];

    protected static function booted(): void
    {
        static::addGlobalScope('operators', fn (Builder $query) => $query->where('role', 'entity'));
        static::updated(function (Operator $operator) {
            if ($operator->wasChanged('entity_id')) {
                $operator->reports()->where('entity_id', $operator->getOriginal('entity_id'))->update(['operator_id' => null]);
            }
        });
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Report::class, 'operator_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function suspend(User $administrator): void
    {
        abort_unless($administrator->role === 'admin', 403);
        static::whereKey($this->id)->whereNull('suspended_at')->update([
            'suspended_at' => now(),
            'session_version' => DB::raw('session_version + 1'),
            'remember_token' => Str::random(60),
        ]);
    }

    public function reactivate(User $administrator): void
    {
        abort_unless($administrator->role === 'admin', 403);
        static::whereKey($this->id)->whereNotNull('suspended_at')->update(['suspended_at' => null]);
    }
}
