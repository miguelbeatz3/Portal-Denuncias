<?php
namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model};

class ReportMessage extends Model
{
    protected $fillable = ['user_id', 'sender', 'message', 'client_token', 'sender_entity_name'];

    public function scopeUnreadBy(Builder $query, User $operator): Builder
    {
        return $query->where('report_messages.sender', 'citizen')->whereNotExists(fn ($reads) => $reads->selectRaw('1')
            ->from('report_message_reads')->where('operator_id', $operator->id)
            ->whereColumn('report_message_reads.report_id', 'report_messages.report_id')
            ->whereColumn('report_message_reads.last_read_message_id', '>=', 'report_messages.id'));
    }

    public function forTracking(): array
    {
        return ['id' => $this->id, 'sender' => $this->sender, 'message' => $this->message,
            'created_at' => $this->created_at->toISOString()]
            + ($this->sender_entity_name !== null ? ['entity_name' => $this->sender_entity_name] : []);
    }

    public function senderLabel(): string
    {
        return match ($this->sender) {
            'admin' => 'Administração',
            'entity' => 'Entidade',
            'citizen' => 'Denunciante',
        };
    }
}
