<?php

namespace App\Models\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_AGENT = 'agent';

    protected $table = 'ai_messages';
    protected $primaryKey = 'id_message';

    protected $fillable = [
        'id_conversation',
        'role',
        'content',
        'kind',
        'intent',
        'id_auteur',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'id_conversation', 'id_conversation');
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'id_auteur', 'id_utilisateur');
    }
}
