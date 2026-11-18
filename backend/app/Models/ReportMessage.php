<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMessage extends Model
{
    protected $fillable = ['user_id', 'sender', 'message', 'client_token'];

    public function forTracking(): array
    {
        return ['id' => $this->id, 'sender' => $this->sender, 'message' => $this->message,
            'created_at' => $this->created_at->toISOString()];
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
