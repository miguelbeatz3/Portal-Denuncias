<?php
namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};

class Report extends Model
{
    public const AWAITING_TREATMENT = ['Encaminhada', 'Reencaminhada'];
    public const ACTIVE = [...self::AWAITING_TREATMENT, 'Em tratamento'];
    public const STATES = ['Recebida' => 'Por triar', 'Encaminhada' => 'Encaminhada', 'Reencaminhada' => 'Reencaminhada',
        'Em tratamento' => 'Em tratamento', 'Concluída' => 'Concluída', 'Arquivada' => 'Arquivada'];

    protected $guarded = ['id'];
    protected $hidden = ['secret_hash'];
    protected $casts = ['evidence_files' => 'array'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function reassignmentRequests(): HasMany
    {
        return $this->hasMany(ReportReassignmentRequest::class);
    }

    public function pendingReassignmentRequest(): HasOne
    {
        return $this->hasOne(ReportReassignmentRequest::class)->whereNull('resolution');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ReportMessage::class);
    }

    public function scopeWithTrackingCredentials(Builder $query, string $reference, string $secret): Builder
    {
        return $query->where('reference', strtoupper($reference))->where('secret_hash', hash('sha256', strtoupper($secret)));
    }
}
