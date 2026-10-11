<?php

namespace App\Services\Ai\Support;

use App\Models\Models\AiConversation;
use App\Models\Models\AiMessage;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;

/**
 * JSON shapes for the Angular client. The agency view adds the audit data
 * (detected intents, tools used, fallbacks) that customers do not need.
 */
class ConversationPresenter
{
    /** @return array<string, mixed> */
    public static function conversation(AiConversation $conversation, bool $forAgency = false): array
    {
        $conversation->loadMissing(['reservation.logement.agence', 'reservation.paiement', 'reservation.confirmation', 'reservation.utilisateur', 'messages.auteur']);
        $agencyName = $conversation->reservation?->logement?->agence?->nom;

        return [
            ...self::header($conversation, $forAgency),
            'messages' => $conversation->messages
                ->map(fn (AiMessage $m) => self::message($m, $agencyName, $forAgency))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function header(AiConversation $conversation, bool $forAgency = false): array
    {
        $reservation = $conversation->reservation;

        $data = [
            'id' => $conversation->id_conversation,
            'statut' => $conversation->statut,
            'handoff_reason' => $forAgency ? $conversation->handoff_reason : null,
            'handoff_requested_at' => $conversation->handoff_requested_at?->toIso8601String(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'reservation' => $reservation ? self::reservation($reservation) : null,
            'confirmation' => $reservation ? self::confirmation($reservation->confirmation) : null,
        ];

        if ($forAgency) {
            $data['customer'] = $reservation?->utilisateur ? [
                'prenom' => $reservation->utilisateur->prenom,
                'nom' => $reservation->utilisateur->nom,
            ] : null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function reservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id_reservation,
            'date_debut' => $reservation->date_debut?->toIso8601String(),
            'date_fin' => $reservation->date_fin?->toIso8601String(),
            'nb_personnes' => (int) $reservation->nb_personnes,
            'statut' => $reservation->statut,
            'montant_total' => ReservationFacts::amount($reservation->montant_total),
            'devise' => $reservation->logement?->devise ?? 'MAD',
            'paiement_statut' => $reservation->paiement?->statut,
            'logement' => $reservation->logement ? [
                'titre' => $reservation->logement->titre,
                'type' => $reservation->logement->type,
                'adresse' => $reservation->logement->adresse,
                'photo_url' => $reservation->logement->photo_url,
            ] : null,
            'agence' => $reservation->logement?->agence ? ['nom' => $reservation->logement->agence->nom] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function confirmation(?ReservationConfirmation $confirmation): ?array
    {
        if (!$confirmation) {
            return null;
        }

        return [
            'intention' => $confirmation->intention,
            'source' => $confirmation->source,
            'requested_at' => $confirmation->requested_at?->toIso8601String(),
            'responded_at' => $confirmation->responded_at?->toIso8601String(),
            'needs_attention' => in_array($confirmation->intention, ReservationConfirmation::NEEDS_AGENCY_ATTENTION, true),
        ];
    }

    /** @return array<string, mixed> */
    public static function message(AiMessage $message, ?string $agencyName, bool $forAgency = false): array
    {
        $meta = $message->meta ?? [];

        $data = [
            'id' => $message->id_message,
            'role' => $message->role,
            'kind' => $message->kind,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
            'author' => match ($message->role) {
                AiMessage::ROLE_ASSISTANT => 'TravelPlan AI',
                AiMessage::ROLE_AGENT => 'Conseiller' . ($agencyName ? " · {$agencyName}" : ''),
                default => null,
            },
            'demo_mode' => ($meta['provider'] ?? null) === 'demo',
            'automated' => (bool) ($meta['automated'] ?? false),
        ];

        if ($forAgency) {
            $data['intent'] = $message->intent;
            $data['audit'] = array_intersect_key($meta, array_flip([
                'provider', 'model', 'fallback', 'fallback_reason', 'tools', 'latency_ms', 'ungrounded_numbers', 'classification',
            ]));
            if ($message->role === AiMessage::ROLE_AGENT && $message->auteur) {
                $data['author'] = $message->auteur->prenom . ' ' . $message->auteur->nom . ' (conseiller)';
            }
        }

        return $data;
    }
}
