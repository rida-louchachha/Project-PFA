<?php

namespace App\Models\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    public const STATUT_ACTIVE = 'active';
    public const STATUT_HANDOFF = 'handoff';
    public const STATUT_CLOSED = 'closed';

    protected $table = 'ai_conversations';
    protected $primaryKey = 'id_conversation';

    protected $fillable = [
        'id_utilisateur',
        'id_reservation',
        'channel',
        'statut',
        'handoff_reason',
        'handoff_requested_at',
        'handoff_resolved_at',
        'handled_by',
        'last_message_at',
    ];

    protected $casts = [
        'handoff_requested_at' => 'datetime',
        'handoff_resolved_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'id_reservation', 'id_reservation');
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'id_conversation', 'id_conversation')->orderBy('id_message');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by', 'id_utilisateur');
    }

    public function requestHandoff(string $reason): void
    {
        if ($this->statut === self::STATUT_HANDOFF) {
            return;
        }

        $this->forceFill([
            'statut' => self::STATUT_HANDOFF,
            'handoff_reason' => mb_substr($reason, 0, 255),
            'handoff_requested_at' => now(),
            'handoff_resolved_at' => null,
        ])->save();
    }
}
