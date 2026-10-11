<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Str;

/**
 * Keyword-based arrival-intention detection (French, English, Moroccan Darija in Latin script).
 * Used by the demo provider and as the offline fallback of the LLM classifier.
 */
final class IntentHeuristics
{
    private const HUMAN = [
        '/\b(parler|contacter|joindre|appeler)\b.*\b(agence|conseiller|humain|quelqu\'?un|agent|responsable)\b/',
        '/\b(un humain|une personne|conseiller|human|real person|speak to|talk to|someone)\b/',
        '/\b(bghit|bghina|briit)\s+n(hder|hdr|tkellem|tkalm)\b/',
        '/\b(reclamation|plainte|complaint|inacceptable)\b/',
    ];

    private const MODIFICATION = [
        '/\b(modifier|changer|decaler|reporter|deplacer|prolonger|raccourcir)\b/',
        '/\b(change|reschedule|modify|postpone|extend)\b/',
        '/\b(nbeddel|nbdl|tbeddel|nbedlo)\b/',
    ];

    private const DECLINED = [
        '/\b(annuler|annulation|annulez|annule)\b/',
        '/\bne (viendrai|viendrons|pourrai|pourrons|venons|viens) (pas|plus)\b/',
        '/\b(plus venir|pas venir)\b/',
        '/\b(cancel|not coming|won\'?t come|can\'?t come|cannot come)\b/',
        '/\b(nlghi|lghi|mabghitch|ma ghadich nji|ma ghadi(ch|nch) nji(w)?)\b/',
    ];

    private const UNCERTAIN = [
        '/\b(pas|plus) (sur|sure|certain|certaine)\b/',
        '/\b(peut[- ]etre|je ne sais pas|sais pas|j\'?hesite|hesite|on verra)\b/',
        '/\b(not sure|maybe|unsure|don\'?t know|not certain)\b/',
        '/\b(ma ?3reftch|ma ?3rftch|mam?t2kedch|yemken|ymkn)\b/',
    ];

    private const CONFIRMED = [
        '/\b(je confirme|confirme|confirmons|toujours bon|c\'?est bon|nous arrivons|on arrive|j\'?arrive|je viens|nous venons|nous serons la|bien sur|absolument|evidemment|tout a fait)\b/',
        '/\b(yes|yeah|yep|confirm(ed)?|we\'?re coming|we are coming|i\'?m coming|still coming|of course|definitely)\b/',
        '/\b(iyeh|eyeh|wakha|safi|ghadi nji(w)?|mconfirmi|akid)\b/',
    ];

    private const WEAK_YES = '/^(oui|ok|okay|d\'?accord|yes)\b|^ah[ .!]*$/';
    private const WEAK_NO = '/^(non|no|nope)\b|^l?la[ .!]*$/';

    private const QUESTION_START = '/^(est[- ]ce|comment|quand|ou |combien|quel|quelle|pourquoi|puis[- ]je|je peux|peut[- ]on|can |could |how |what |when |where |is |are |do |does |wach |imta|fin |ch7al|chhal|bch7al)/';
    private const DECISION_VERB = '/\b(je (veux|souhaite|voudrais|prefere)|nous (voulons|souhaitons|voudrions)|i (want|would like|need) to|bghit|bghina)\b/';

    private const DARIJA = '/\b(iyeh|wakha|safi|bghit|bghina|ghadi|nji|wach|imta|fin|ch7al|chhal|mabghitch|lghi|nbeddel|3afak|chokran|salam|labas|mzyan)\b/';
    private const ENGLISH = '/\b(the|yes|please|my|booking|coming|cancel|thanks|hello|hi|when|where|how)\b/';

    public static function normalize(string $text): string
    {
        $text = str_replace(['’', '‘', '`'], "'", $text);
        $text = Str::lower(Str::ascii($text));

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * @return array{intention: string, confidence: float, language: string}
     */
    public static function classify(string $message, bool $awaitingAnswer = false): array
    {
        $text = self::normalize($message);
        $language = self::language($text, $message);
        $result = fn (string $intention, float $confidence) => [
            'intention' => $intention,
            'confidence' => $confidence,
            'language' => $language,
        ];

        if ($text === '') {
            return $result('none', 0.0);
        }

        // Questions ("Est-ce que je peux annuler ?") are not decisions, unless phrased as one
        $isQuestion = str_contains($message, '?') || preg_match(self::QUESTION_START, $text) === 1;
        if ($isQuestion && preg_match(self::DECISION_VERB, $text) !== 1) {
            return $result('none', 0.3);
        }

        foreach ([
            'human_assistance_required' => [self::HUMAN, 0.9],
            'modification_requested' => [self::MODIFICATION, 0.85],
            'declined' => [self::DECLINED, 0.85],
            'uncertain' => [self::UNCERTAIN, 0.8],
            'confirmed' => [self::CONFIRMED, 0.9],
        ] as $intention => [$patterns, $confidence]) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    return $result($intention, $confidence);
                }
            }
        }

        // A bare "oui" / "non" only counts as an answer when a question about arrival is pending
        if (preg_match(self::WEAK_YES, $text) === 1) {
            return $result('confirmed', $awaitingAnswer ? 0.8 : 0.4);
        }
        if (preg_match(self::WEAK_NO, $text) === 1) {
            return $result('declined', $awaitingAnswer ? 0.7 : 0.4);
        }

        return $result('none', 0.5);
    }

    public static function language(string $normalized, string $raw = ''): string
    {
        if (preg_match('/\p{Arabic}/u', $raw) === 1 || preg_match(self::DARIJA, $normalized) === 1) {
            return 'darija';
        }

        return preg_match(self::ENGLISH, $normalized) === 1 ? 'en' : 'fr';
    }
}
