<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMessage extends Model
{
    protected $fillable = ['user_id', 'sender', 'message', 'client_token', 'sender_entity_name'];

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
