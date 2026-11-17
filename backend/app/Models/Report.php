<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['secret_hash'];
    protected $casts = ['evidence_files' => 'array'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
