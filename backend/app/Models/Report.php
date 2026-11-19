<?php
namespace App\Models;

use Carbon\CarbonImmutable;
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
    protected $hidden = ['secret_hash', 'awaiting_reply_since', 'next_action_on'];
    protected $casts = ['evidence_files' => 'array', 'awaiting_reply_since' => 'datetime', 'next_action_on' => 'immutable_date'];

    public function isAwaitingReply(): bool
    {
        return $this->status === 'Em tratamento' && $this->awaiting_reply_since !== null;
    }

    public function hasOverdueNextAction(): bool
    {
        return in_array($this->status, self::ACTIVE, true) && $this->next_action_on !== null
            && $this->next_action_on->toDateString() < CarbonImmutable::today('Africa/Luanda')->toDateString();
    }

    public function scopeWithOverdueNextAction(Builder $query): Builder
    {
        return $query->whereIn('reports.status', self::ACTIVE)
            ->where('reports.next_action_on', '<', CarbonImmutable::today('Africa/Luanda')->toDateString());
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
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

    public function internalNotes(): HasMany
    {
        return $this->hasMany(ReportInternalNote::class);
    }

    public function scopeWithTrackingCredentials(Builder $query, string $reference, string $secret): Builder
    {
        return $query->where('reference', strtoupper($reference))->where('secret_hash', hash('sha256', strtoupper($secret)));
    }
}
