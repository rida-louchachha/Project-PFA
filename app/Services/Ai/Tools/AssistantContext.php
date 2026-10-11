<?php

namespace App\Services\Ai\Tools;

use App\Models\Models\AiConversation;
use App\Models\Models\Reservation;
use App\Models\User;

/**
 * Server-side identity of the current assistant turn. Built from the authenticated
 * Sanctum user, never from anything the model or the customer message says.
 */
final class AssistantContext
{
    public function __construct(
        public readonly User $user,
        public readonly AiConversation $conversation,
        public readonly ?Reservation $reservation,
    ) {
    }
}
