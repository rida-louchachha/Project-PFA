<?php

namespace App\Services\Ai\Support;

use App\Models\Models\Reservation;
use App\Support\Currency;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Converts database records into the minimal set of facts the assistant may use.
 * Every value shown to the customer or sent to the LLM comes from here, read from MySQL.
 * Customer email, password or payment references are deliberately not included.
 */
class ReservationFacts
{
    private const RESERVATION_STATUS = [
        'en_attente' => 'en attente de paiement',
        'confirme' => 'confirmée',
        'annule' => 'annulée',
    ];

    private const PAYMENT_STATUS = [
        'en_attente' => 'en attente',
        'regle' => 'réglé',
        'echoue' => 'échoué',
        'rembourse' => 'remboursé',
    ];

    private const PAYMENT_MODE = [
        'especes' => 'espèces',
        'terminal' => 'terminal de paiement',
        'en_ligne' => 'paiement en ligne',
    ];

    private const TYPE = [
        'hotel' => 'hôtel',
        'maison' => 'maison',
        'studio' => 'studio',
    ];

    /**
     * Full facts for one reservation (assistant tool output).
     *
     * @return array<string, mixed>
     */
    public static function details(Reservation $reservation): array
    {
        $reservation->loadMissing(['logement.agence', 'paiement', 'confirmation']);

        $logement = $reservation->logement;
        $agence = $logement?->agence;
        $paiement = $reservation->paiement;
        $start = Carbon::parse($reservation->date_debut);
        $end = Carbon::parse($reservation->date_fin);

        return [
            'reservation_id' => $reservation->id_reservation,
            'statut' => $reservation->statut,
            'statut_label' => self::RESERVATION_STATUS[$reservation->statut] ?? $reservation->statut,
            'date_arrivee' => $start->format('Y-m-d H:i'),
            'date_arrivee_label' => self::dateLabel($start),
            'date_depart' => $end->format('Y-m-d H:i'),
            'date_depart_label' => self::dateLabel($end),
            'nb_personnes' => (int) $reservation->nb_personnes,
            'montant_total' => self::amount($reservation->montant_total),
            'devise' => Currency::label($logement?->devise),
            'paiement' => $paiement ? [
                'statut' => $paiement->statut,
                'statut_label' => self::PAYMENT_STATUS[$paiement->statut] ?? $paiement->statut,
                'mode' => self::PAYMENT_MODE[$paiement->mode] ?? $paiement->mode,
                'montant' => self::amount($paiement->montant),
                'date' => $paiement->date_paiement ? Carbon::parse($paiement->date_paiement)->format('Y-m-d H:i') : null,
            ] : null,
            'logement' => $logement ? [
                'titre' => $logement->titre,
                'type' => self::TYPE[$logement->type] ?? $logement->type,
                'adresse' => $logement->adresse,
                'capacite' => (int) $logement->capacite,
            ] : null,
            'agence' => $agence ? [
                'nom' => $agence->nom,
                'email' => $agence->email,
                'telephone' => $agence->telephone,
            ] : null,
            'intention_arrivee' => $reservation->confirmation?->intention,
        ];
    }

    /**
     * Compact line for reservation lists.
     *
     * @return array<string, mixed>
     */
    public static function summary(Reservation $reservation): array
    {
        $reservation->loadMissing(['logement', 'paiement', 'confirmation']);

        return [
            'reservation_id' => $reservation->id_reservation,
            'logement' => $reservation->logement?->titre,
            'adresse' => $reservation->logement?->adresse,
            'date_arrivee' => Carbon::parse($reservation->date_debut)->format('Y-m-d H:i'),
            'date_depart' => Carbon::parse($reservation->date_fin)->format('Y-m-d H:i'),
            'statut' => $reservation->statut,
            'statut_label' => self::RESERVATION_STATUS[$reservation->statut] ?? $reservation->statut,
            'paiement_statut' => $reservation->paiement?->statut,
            'montant_total' => self::amount($reservation->montant_total),
            'devise' => Currency::label($reservation->logement?->devise),
            'intention_arrivee' => $reservation->confirmation?->intention,
        ];
    }

    public static function dateLabel(CarbonInterface $date): string
    {
        return $date->copy()->locale('fr')->translatedFormat('j F Y');
    }

    public static function amount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    public static function reservationStatusLabel(string $status): string
    {
        return self::RESERVATION_STATUS[$status] ?? $status;
    }
}
