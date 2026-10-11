<?php

namespace App\Services\Ai\Tools;

use App\Models\Models\Reservation;
use App\Services\Ai\Support\ReservationFacts;
use App\Services\Booking\BookingPolicy;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Explicit allowlist of what TravelPlan AI can do.
 *
 * - Every tool is scoped to the authenticated customer (AssistantContext::$user).
 * - Inputs proposed by the model are validated here, server-side.
 * - Read-only, except `request_human_assistance`, which only flags the conversation.
 * - There is deliberately no tool to cancel, refund, modify dates or touch payments,
 *   and no generic database / SQL access.
 */
class ToolRegistry
{
    /** @var list<array{name: string, ok: bool, ms: int}> */
    private array $calls = [];

    /** @var list<array<string, mixed>> */
    private array $outputs = [];

    public function __construct(
        private readonly AssistantContext $context,
        private readonly BookingPolicy $policy,
    ) {
    }

    /**
     * Provider-neutral tool definitions (JSON Schema, strict-compatible).
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function definitions(): array
    {
        $reservationId = [
            'type' => 'object',
            'properties' => [
                'reservation_id' => [
                    'type' => 'integer',
                    'description' => 'Identifiant de la réservation (champ reservation_id).',
                ],
            ],
            'required' => ['reservation_id'],
            'additionalProperties' => false,
        ];

        return [
            [
                'name' => 'list_my_reservations',
                'description' => "Liste les réservations du client connecté (les 10 plus récentes) : logement, dates, statut de la réservation, statut du paiement, montant et intention d'arrivée. À utiliser quand le client parle de ses réservations sans préciser laquelle.",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'get_reservation_details',
                'description' => "Détails d'une réservation du client connecté : dates d'arrivée et de départ, nombre de personnes, montant total, paiement (statut, mode, montant), logement (titre, type, adresse), coordonnées de l'agence, intention d'arrivée enregistrée. Source unique pour toute question factuelle sur une réservation.",
                'input_schema' => $reservationId,
            ],
            [
                'name' => 'get_cancellation_policy',
                'description' => "Règles d'annulation et de remboursement appliquées par TravelPlan pour une réservation du client, avec l'éligibilité calculée maintenant. L'assistant ne peut pas annuler lui-même : cet outil donne la procédure à suivre.",
                'input_schema' => $reservationId,
            ],
            [
                'name' => 'request_human_assistance',
                'description' => "Transfère la conversation à un conseiller humain de l'agence (demande de modification de dates, réclamation, demande explicite de parler à l'agence, ou question à laquelle les autres outils ne répondent pas).",
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Résumé court et factuel de la demande du client (une phrase).',
                        ],
                    ],
                    'required' => ['reason'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_column($this->definitions(), 'name');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input): ToolOutcome
    {
        $started = microtime(true);

        try {
            $outcome = match ($name) {
                'list_my_reservations' => $this->listMyReservations(),
                'get_reservation_details' => $this->withOwnedReservation($input, fn (Reservation $r) => ToolOutcome::success(ReservationFacts::details($r))),
                'get_cancellation_policy' => $this->withOwnedReservation($input, fn (Reservation $r) => $this->cancellationPolicy($r)),
                'request_human_assistance' => $this->requestHumanAssistance($input),
                default => ToolOutcome::failure('unknown_tool', "Outil non autorisé : {$name}."),
            };
        } catch (\Throwable $e) {
            Log::error('TravelPlan AI tool failed', ['tool' => $name, 'exception' => $e->getMessage()]);
            $outcome = ToolOutcome::failure('tool_error', "L'information n'a pas pu être récupérée.");
        }

        $this->calls[] = ['name' => $name, 'ok' => $outcome->ok, 'ms' => (int) round((microtime(true) - $started) * 1000)];
        $this->outputs[] = $outcome->payload;

        return $outcome;
    }

    /** @return list<array{name: string, ok: bool, ms: int}> */
    public function calls(): array
    {
        return $this->calls;
    }

    /** @return list<array<string, mixed>> All data returned to the model during this turn (grounding check). */
    public function outputs(): array
    {
        return $this->outputs;
    }

    public function context(): AssistantContext
    {
        return $this->context;
    }

    private function listMyReservations(): ToolOutcome
    {
        $reservations = Reservation::with(['logement', 'paiement', 'confirmation'])
            ->where('id_utilisateur', $this->context->user->id_utilisateur)
            ->orderByDesc('date_debut')
            ->limit(10)
            ->get();

        return ToolOutcome::success([
            'total' => $reservations->count(),
            'reservations' => $reservations->map(fn (Reservation $r) => ReservationFacts::summary($r))->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  callable(Reservation): ToolOutcome  $callback
     */
    private function withOwnedReservation(array $input, callable $callback): ToolOutcome
    {
        $validator = Validator::make($input, ['reservation_id' => ['required', 'integer', 'min:1']]);
        if ($validator->fails()) {
            return ToolOutcome::failure('invalid_input', 'reservation_id doit être un entier.');
        }

        // Ownership check: a customer only ever sees their own reservations
        $reservation = Reservation::where('id_reservation', (int) $input['reservation_id'])
            ->where('id_utilisateur', $this->context->user->id_utilisateur)
            ->first();

        if (!$reservation) {
            return ToolOutcome::failure('reservation_not_found', "Aucune réservation avec cet identifiant n'existe pour ce client.");
        }

        return $callback($reservation);
    }

    private function cancellationPolicy(Reservation $reservation): ToolOutcome
    {
        $reservation->loadMissing(['paiement', 'logement']);
        $now = Carbon::now();
        $checkIn = Carbon::parse($reservation->date_debut);
        $check = $this->policy->cancellationCheck($reservation, $now);
        $paid = $reservation->paiement?->statut === 'regle';
        $rate = $this->policy->refundRate($checkIn, $now);

        return ToolOutcome::success([
            'reservation_id' => $reservation->id_reservation,
            'statut' => $reservation->statut,
            'annulation_possible_maintenant' => $check['allowed'],
            'motif_refus' => match ($check['reason']) {
                'already_cancelled' => 'La réservation est déjà annulée.',
                'less_than_24h_before_checkin' => "L'arrivée est dans moins de 24 heures (ou déjà passée).",
                default => null,
            },
            'heures_avant_arrivee' => (int) floor($now->diffInHours($checkIn, false)),
            'regle_annulation' => "Annulation possible jusqu'à 24 heures avant la date d'arrivée.",
            'procedure' => "Le client annule lui-même depuis la page « Mes réservations » de TravelPlan (bouton d'annulation). L'assistant ne peut pas annuler, rembourser ni modifier une réservation.",
            'modification_dates' => "TravelPlan ne permet pas de modifier les dates en ligne : le client doit contacter l'agence ou demander un conseiller dans cette conversation.",
            'remboursement' => [
                'paiement_regle' => $paid,
                'bareme' => [
                    "Plus de 7 jours avant l'arrivée : 100 %",
                    "Entre 3 et 7 jours avant l'arrivée : 50 %",
                    "Moins de 3 jours avant l'arrivée : 25 %",
                ],
                'taux_si_annulation_maintenant' => $paid ? (int) round($rate * 100) : null,
                'montant_estime' => $paid ? ReservationFacts::amount((float) $reservation->paiement->montant * $rate) : null,
                'devise' => Currency::label($reservation->logement?->devise),
                'note' => $paid
                    ? 'Montant indicatif, calculé selon le barème au moment de la demande de remboursement.'
                    : "Aucun paiement réglé n'est enregistré : aucun remboursement ne s'applique.",
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requestHumanAssistance(array $input): ToolOutcome
    {
        $validator = Validator::make($input, ['reason' => ['required', 'string', 'max:300']]);
        if ($validator->fails()) {
            return ToolOutcome::failure('invalid_input', 'reason est requis (300 caractères maximum).');
        }

        $conversation = $this->context->conversation;

        if (!$conversation->id_reservation) {
            return ToolOutcome::failure(
                'no_reservation',
                "Cette conversation n'est liée à aucune réservation : le client doit ouvrir l'assistant depuis la réservation concernée pour joindre l'agence."
            );
        }

        $conversation->requestHandoff('Assistant : ' . strip_tags((string) $input['reason']));
        $agency = $conversation->reservation?->logement?->agence;

        return ToolOutcome::success([
            'transfert' => 'effectué',
            'agence' => $agency?->nom,
            'message' => "Un conseiller de l'agence a été notifié et répondra dans cette conversation.",
        ]);
    }
}
