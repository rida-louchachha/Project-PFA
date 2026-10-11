<?php

namespace App\Policies;

use App\Models\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    /** The customer who owns the conversation. */
    public function participate(User $user, AiConversation $conversation): bool
    {
        return (int) $conversation->id_utilisateur === (int) $user->id_utilisateur;
    }

    /**
     * An agency account may handle a conversation only if it is tied to a reservation
     * of one of ITS accommodations (agences.id_utilisateur ownership).
     */
    public function handleAsAgency(User $user, AiConversation $conversation): bool
    {
        if (!$user->isAgency() || !$conversation->id_reservation) {
            return false;
        }

        $agencyId = $user->agence?->id_agence;
        $conversation->loadMissing('reservation.logement');

        return $agencyId !== null
            && (int) $conversation->reservation?->logement?->id_agence === (int) $agencyId;
    }
}
