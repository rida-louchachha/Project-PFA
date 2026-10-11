<?php

namespace App\Services\Ai\Providers;

use App\Models\Models\ReservationConfirmation;
use App\Services\Ai\ChatResult;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Tools\ToolRegistry;

/**
 * Deterministic "demo mode" engine used when no API key is configured, and as the
 * automatic fallback when the LLM API is unavailable.
 *
 * It understands common French / English / Darija phrasings by keywords and answers
 * (in French) only from the same allowlisted tools the LLM uses — so even offline,
 * every fact comes from MySQL and the same authorization rules apply.
 */
class DemoLanguageModel implements LanguageModel
{
    private const TOPICS = [
        'list' => '/\b(mes reservations|toutes mes|my (bookings|reservations)|liste|list|montre|show)\b/',
        'agency' => '/\b(parler|contacter|joindre|appeler|agence|conseiller|humain|agent|speak|talk|human|bghit nhder)\b/',
        'cancel' => '/\b(annul\w*|cancel\w*|rembours\w*|refund\w*|lghi|nlghi)\b/',
        'modify' => '/\b(modifier|changer|decaler|reporter|change|reschedule|nbeddel)\b/',
        'payment' => '/\b(paye|payee|payer|paiement|combien|prix|montant|tarif|cout|paid|pay|price|how much|cost|ch7al|chhal|flous)\b/',
        'location' => '/\b(ou se trouve|ou est|adresse|situe|localisation|where|address|location|fin kayn)\b/',
        'dates' => '/\b(quand|date|commence|debut|arrivee|depart|fin du sejour|when|start|check-?in|check-?out|imta)\b/',
        'status' => '/\b(confirme|confirmee|statut|status|valide|etat)\b/',
    ];

    public function provider(): string
    {
        return 'demo';
    }

    public function model(): string
    {
        return 'travelplan-rules-v1';
    }

    public function chat(string $system, array $history, ToolRegistry $tools, array $hints = []): ChatResult
    {
        $last = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['role'] === 'user') {
                $last = (string) $history[$i]['content'];
                break;
            }
        }

        $context = $tools->context();
        $text = IntentHeuristics::normalize($last);
        $recorded = $hints['recorded_intention'] ?? null;

        if ($recorded && ($ack = $this->acknowledgeIntention($recorded, $tools)) !== null) {
            return new ChatResult($ack);
        }

        $topic = $this->topic($text);
        $reservationId = $context->reservation?->id_reservation;
        $prefix = '';

        // General rules do not need a specific booking
        if ($reservationId === null && $topic === 'cancel') {
            return new ChatResult($this->generalPolicy());
        }

        // General conversation: answer about the customer's next stay
        if ($reservationId === null && in_array($topic, ['dates', 'payment', 'location', 'status', 'agency'], true)
            && ($next = $this->nextStay($tools)) !== null) {
            $reservationId = $next['reservation_id'];
            $prefix = "Pour votre prochain séjour (réservation n°{$next['reservation_id']}, « {$next['logement']} ») : ";
        }

        if ($topic === 'list' || ($reservationId === null && $topic !== 'help')) {
            return new ChatResult($this->listReservations($tools, $topic !== 'list'));
        }

        $reply = match ($topic) {
            'dates' => $this->dates($tools, $reservationId),
            'payment' => $this->payment($tools, $reservationId),
            'location' => $this->location($tools, $reservationId),
            'status' => $this->status($tools, $reservationId),
            'cancel' => $this->cancellation($tools, $reservationId),
            'modify' => $this->modification($tools),
            'agency' => $this->agency($tools, $reservationId),
            default => $this->help($context->user->prenom),
        };

        return new ChatResult($prefix !== '' ? $prefix . lcfirst($reply) : $reply);
    }

    /** Next non-cancelled stay that has not ended yet (from the customer's own reservations). */
    private function nextStay(ToolRegistry $tools): ?array
    {
        $outcome = $tools->execute('list_my_reservations', []);
        if (!$outcome->ok) {
            return null;
        }

        $now = now()->format('Y-m-d H:i');
        $upcoming = array_filter(
            $outcome->payload['reservations'],
            fn (array $r) => $r['statut'] !== 'annule' && $r['date_depart'] >= $now,
        );
        usort($upcoming, fn (array $a, array $b) => strcmp($a['date_arrivee'], $b['date_arrivee']));

        return $upcoming[0] ?? null;
    }

    public function extract(string $task, string $system, string $input, array $schema, array $context = []): array
    {
        return match ($task) {
            'arrival_intention' => IntentHeuristics::classify((string) ($context['customer_message'] ?? $input), (bool) ($context['awaiting_answer'] ?? false)),
            'smart_search' => \App\Services\Ai\Search\SearchHeuristics::parse(
                preg_match('/<<<\n(.*)\n>>>/s', $input, $m) ? $m[1] : $input,
                $context['cities'] ?? [],
            ),
            default => [],
        };
    }

    public function write(string $system, string $instruction): ?string
    {
        // No free-text generation in demo mode: callers use their templates
        return null;
    }

    private function topic(string $text): string
    {
        foreach (self::TOPICS as $topic => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $topic;
            }
        }

        return 'help';
    }

    private function details(ToolRegistry $tools, int $reservationId): ?array
    {
        $outcome = $tools->execute('get_reservation_details', ['reservation_id' => $reservationId]);

        return $outcome->ok ? $outcome->payload : null;
    }

    private function unavailable(): string
    {
        return "Je ne parviens pas à retrouver cette réservation. Vous pouvez consulter « Mes réservations » ou me poser la question depuis la réservation concernée.";
    }

    private function listReservations(ToolRegistry $tools, bool $askWhich): string
    {
        $outcome = $tools->execute('list_my_reservations', []);
        $reservations = $outcome->ok ? $outcome->payload['reservations'] : [];

        if ($reservations === []) {
            return "Je ne trouve aucune réservation sur votre compte pour le moment. Dès que vous réserverez un logement, je pourrai vous aider à suivre votre séjour.";
        }

        $lines = array_map(
            fn (array $r) => sprintf(
                '• n°%d — %s : du %s au %s, réservation %s',
                $r['reservation_id'],
                $r['logement'] ?? 'logement',
                substr($r['date_arrivee'], 0, 10),
                substr($r['date_depart'], 0, 10),
                $r['statut_label'],
            ),
            array_slice($reservations, 0, 5),
        );

        $intro = $askWhich
            ? 'Voici vos réservations. Ouvrez l\'assistant depuis la réservation concernée (bouton « Assistant IA » dans « Mes réservations ») pour que je réponde précisément :'
            : 'Voici vos réservations les plus récentes :';

        return $intro . "\n" . implode("\n", $lines);
    }

    private function dates(ToolRegistry $tools, int $reservationId): string
    {
        $d = $this->details($tools, $reservationId);
        if (!$d) {
            return $this->unavailable();
        }

        return sprintf(
            'Votre séjour au « %s » commence le %s%s et se termine le %s%s, pour %d personne(s).',
            $d['logement']['titre'] ?? 'logement',
            $d['date_arrivee_label'],
            $this->timeSuffix($d['date_arrivee']),
            $d['date_depart_label'],
            $this->timeSuffix($d['date_depart']),
            $d['nb_personnes'],
        );
    }

    /** " à 14:00", or nothing when only a date was booked (stored as midnight). */
    private function timeSuffix(string $dateTime): string
    {
        $time = substr($dateTime, 11, 5);

        return $time === '' || $time === '00:00' ? '' : " à {$time}";
    }

    private function payment(ToolRegistry $tools, int $reservationId): string
    {
        $d = $this->details($tools, $reservationId);
        if (!$d) {
            return $this->unavailable();
        }

        $total = "Le montant total de la réservation est de {$d['montant_total']} {$d['devise']}.";

        if (!$d['paiement']) {
            return $total . " Je ne trouve aucun paiement enregistré pour cette réservation.";
        }

        $p = $d['paiement'];

        return $total . sprintf(
            ' Paiement enregistré : %s %s par %s, statut « %s »%s.',
            $p['montant'],
            $d['devise'],
            $p['mode'],
            $p['statut_label'],
            $p['date'] ? ' (le ' . substr($p['date'], 0, 10) . ')' : '',
        );
    }

    private function location(ToolRegistry $tools, int $reservationId): string
    {
        $d = $this->details($tools, $reservationId);
        if (!$d || !$d['logement']) {
            return $this->unavailable();
        }

        if (empty($d['logement']['adresse'])) {
            return "L'adresse de « {$d['logement']['titre']} » n'est pas renseignée dans TravelPlan. "
                . $this->agencyContactSentence($d['agence']);
        }

        return "« {$d['logement']['titre']} » ({$d['logement']['type']}) se trouve à l'adresse suivante : {$d['logement']['adresse']}.";
    }

    private function status(ToolRegistry $tools, int $reservationId): string
    {
        $d = $this->details($tools, $reservationId);
        if (!$d) {
            return $this->unavailable();
        }

        $payment = $d['paiement']
            ? " Le paiement est « {$d['paiement']['statut_label']} »."
            : ' Aucun paiement n\'est encore enregistré.';

        return "Votre réservation n°{$d['reservation_id']} est actuellement {$d['statut_label']}.{$payment}";
    }

    private function cancellation(ToolRegistry $tools, int $reservationId): string
    {
        $outcome = $tools->execute('get_cancellation_policy', ['reservation_id' => $reservationId]);
        if (!$outcome->ok) {
            return $this->unavailable();
        }

        $p = $outcome->payload;
        $parts = [$p['regle_annulation']];

        $parts[] = $p['annulation_possible_maintenant']
            ? 'Votre réservation peut encore être annulée : vous pouvez le faire vous-même depuis la page « Mes réservations ».'
            : "Elle ne peut plus être annulée en ligne : {$p['motif_refus']}";

        if ($p['remboursement']['paiement_regle']) {
            $parts[] = sprintf(
                'Barème de remboursement : %s. En annulant maintenant, le remboursement indicatif serait de %d %% (environ %s %s).',
                implode(' ; ', $p['remboursement']['bareme']),
                $p['remboursement']['taux_si_annulation_maintenant'],
                $p['remboursement']['montant_estime'],
                $p['remboursement']['devise'],
            );
        } else {
            $parts[] = $p['remboursement']['note'];
        }

        $parts[] = "Je ne peux pas annuler à votre place.";

        return implode(' ', $parts);
    }

    /** Same rules as BookingPolicy (and the get_cancellation_policy tool), without a booking. */
    private function generalPolicy(): string
    {
        return "Sur TravelPlan, une réservation peut être annulée jusqu'à 24 heures avant la date d'arrivée, directement depuis la page « Mes réservations ». "
            . "Pour une réservation payée, le remboursement dépend du délai avant l'arrivée : plus de 7 jours : 100 % ; entre 3 et 7 jours : 50 % ; moins de 3 jours : 25 %. "
            . 'Je ne peux pas annuler à votre place. Pour connaître le montant exact, ouvrez l\'assistant depuis la réservation concernée.';
    }

    private function modification(ToolRegistry $tools): string
    {
        $outcome = $tools->execute('request_human_assistance', ['reason' => 'Le client souhaite modifier sa réservation.']);

        return "La modification des dates n'est pas automatique sur TravelPlan. "
            . ($outcome->ok
                ? "J'ai transmis votre demande à l'agence : un conseiller vous répondra directement dans cette conversation."
                : $this->openFromReservationHint());
    }

    private function openFromReservationHint(): string
    {
        return "Pour qu'un conseiller de l'agence vous réponde directement ici, ouvrez l'assistant depuis la réservation concernée (bouton « Assistant IA » dans « Mes réservations »).";
    }

    private function agency(ToolRegistry $tools, int $reservationId): string
    {
        $d = $this->details($tools, $reservationId);
        $outcome = $tools->execute('request_human_assistance', ['reason' => "Le client souhaite parler à l'agence."]);

        $reply = $outcome->ok
            ? "C'est noté : un conseiller de l'agence a été notifié et vous répondra dans cette conversation."
            : $this->openFromReservationHint();

        return $reply . ($d ? ' ' . $this->agencyContactSentence($d['agence']) : '');
    }

    private function agencyContactSentence(?array $agence): string
    {
        if (!$agence) {
            return "Les coordonnées de l'agence ne sont pas disponibles.";
        }

        $contacts = array_filter([$agence['telephone'] ?? null, $agence['email'] ?? null]);

        return $contacts
            ? "Vous pouvez aussi joindre l'agence {$agence['nom']} : " . implode(' / ', $contacts) . '.'
            : "Les coordonnées de l'agence {$agence['nom']} ne sont pas renseignées.";
    }

    private function acknowledgeIntention(string $intention, ToolRegistry $tools): ?string
    {
        $reservationId = $tools->context()->reservation?->id_reservation;

        return match ($intention) {
            ReservationConfirmation::CONFIRMED => "Merci pour votre confirmation ! J'ai bien noté que vous prévoyez toujours de venir. L'agence en est informée. N'hésitez pas si vous avez une question sur votre séjour.",
            ReservationConfirmation::UNCERTAIN => "C'est noté, vous n'êtes pas encore sûr. Vous pourrez me reconfirmer plus tard. Si vous le souhaitez, je peux vous rappeler les conditions d'annulation.",
            ReservationConfirmation::DECLINED => "C'est noté, vous ne pensez plus venir. Votre réservation n'est pas annulée automatiquement. "
                . ($reservationId ? $this->cancellation($tools, $reservationId) : ''),
            ReservationConfirmation::MODIFICATION_REQUESTED => $this->modification($tools),
            ReservationConfirmation::HUMAN_ASSISTANCE_REQUIRED => "C'est noté : un conseiller de l'agence a été notifié et vous répondra directement dans cette conversation.",
            default => null,
        };
    }

    private function help(string $firstName): string
    {
        return "Bonjour {$firstName} ! Je suis TravelPlan AI, l'assistant de réservation. Je peux vous indiquer les dates, l'adresse, le montant et le statut du paiement de votre réservation, vous expliquer les conditions d'annulation, ou transmettre votre demande à l'agence. Que souhaitez-vous savoir ?";
    }
}
