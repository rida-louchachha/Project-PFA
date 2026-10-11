<?php

namespace App\Services\Ai\Search;

use App\Services\Ai\Providers\IntentHeuristics;
use Illuminate\Support\Carbon;

/**
 * Deterministic natural-language → filters parser (French / English / Darija keywords).
 * Used in demo mode and as the fallback of the LLM extraction.
 */
final class SearchHeuristics
{
    private const CITY_ALIASES = [
        'casa' => 'Casablanca',
        'fes' => 'Fès',
        'fez' => 'Fès',
        'chaouen' => 'Chefchaouen',
        'desert' => 'Merzouga',
        'sahara' => 'Merzouga',
        'mogador' => 'Essaouira',
        'tangier' => 'Tanger',
        'marrakesh' => 'Marrakech',
        'kech' => 'Marrakech',
    ];

    private const TYPES = [
        'maison' => '/\b(riad|riads|maison|villa|kasbah|dar|house|home|maison d\'hotes)\b/',
        'hotel' => '/\b(hotel|hotels|camp|bivouac|resort)\b/',
        'studio' => '/\b(studio|studios|appartement|appart|apartment|flat)\b/',
    ];

    private const KEYWORDS = [
        'piscine' => '/\b(piscine|pool)\b/',
        'plage' => '/\b(plage|beach|mer|ocean|bord de mer)\b/',
        'médina' => '/\b(medina)\b/',
        'surf' => '/\b(surf)\b/',
        'hammam' => '/\b(hammam|spa)\b/',
        'vue' => '/\b(vue|view|panorama|panoramique)\b/',
        'calme' => '/\b(calme|quiet|tranquille)\b/',
        'luxe' => '/\b(luxe|luxury|luxueux)\b/',
        'famille' => '/\b(famille|family|enfants|kids)\b/',
    ];

    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    /**
     * @param  list<string>  $cities  Cities that exist in the catalogue
     * @return array{ville: ?string, type: ?string, nb_personnes: ?int, prix_max: ?float, date_arrivee: ?string, nuits: ?int, mots_cles: list<string>}
     */
    public static function parse(string $query, array $cities, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $text = IntentHeuristics::normalize($query);

        return [
            'ville' => self::city($text, $cities),
            'type' => self::type($text),
            'nb_personnes' => self::guests($text),
            'prix_max' => self::budget($text),
            ...self::dates($text, $today),
            'mots_cles' => array_keys(array_filter(self::KEYWORDS, fn ($p) => preg_match($p, $text) === 1)),
        ];
    }

    private static function city(string $text, array $cities): ?string
    {
        foreach ($cities as $city) {
            if (preg_match('/\b' . preg_quote(IntentHeuristics::normalize($city), '/') . '\b/', $text) === 1) {
                return $city;
            }
        }

        foreach (self::CITY_ALIASES as $alias => $city) {
            if (in_array($city, $cities, true) && preg_match('/\b' . preg_quote($alias, '/') . '\b/', $text) === 1) {
                return $city;
            }
        }

        return null;
    }

    private static function type(string $text): ?string
    {
        foreach (self::TYPES as $type => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $type;
            }
        }

        return null;
    }

    private static function guests(string $text): ?int
    {
        if (preg_match('/(\d{1,2})\s*(personnes?|pers\b|adultes?|voyageurs?|people|persons|guests|adults|nfar)/', $text, $m)) {
            return (int) $m[1];
        }

        return match (true) {
            preg_match('/\b(en couple|a deux|pour deux|for two|couple)\b/', $text) === 1 => 2,
            preg_match('/\b(en famille|family|famille)\b/', $text) === 1 => 4,
            preg_match('/\b(seul|solo|alone|une personne)\b/', $text) === 1 => 1,
            preg_match('/\b(entre amis|with friends|groupe|group)\b/', $text) === 1 => 4,
            default => null,
        };
    }

    private static function budget(string $text): ?float
    {
        $text = preg_replace('/(?<=\d)[\s.](?=\d{3}\b)/', '', $text) ?? $text;

        if (preg_match('/(moins de|max(?:imum)?|budget(?: de)?|jusqu\'?a|under|below|less than|<)\s*(\d{2,6})/', $text, $m)) {
            return (float) $m[2];
        }
        if (preg_match('/(\d{2,6})\s*(dh|mad|dirhams?|drh)\b/', $text, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    /**
     * @return array{date_arrivee: ?string, nuits: ?int}
     */
    private static function dates(string $text, Carbon $today): array
    {
        $nights = preg_match('/(\d{1,2})\s*(nuits?|nights?|jours?|days?|lyali)/', $text, $m) ? (int) $m[1] : null;
        $start = null;

        if (preg_match('/\bdu (\d{1,2})(?: au (\d{1,2}))? (' . implode('|', array_keys(self::MONTHS)) . ')/', $text, $m)) {
            $start = Carbon::create($today->year, self::MONTHS[$m[3]], (int) $m[1]);
            if ($start->lt($today)) {
                $start->addYear();
            }
            if (!empty($m[2]) && (int) $m[2] > (int) $m[1]) {
                $nights ??= (int) $m[2] - (int) $m[1];
            }
        } elseif (preg_match('/\b(ce week-?end|this weekend|weekend)\b/', $text)) {
            $start = $today->isFriday() || $today->isSaturday() ? $today->copy() : $today->copy()->next(Carbon::FRIDAY);
            $nights ??= $today->isSaturday() ? 1 : 2;
        } elseif (preg_match('/\b(demain|tomorrow|ghda)\b/', $text)) {
            $start = $today->copy()->addDay();
        } elseif (preg_match('/\b(semaine prochaine|next week)\b/', $text)) {
            $start = $today->copy()->next(Carbon::MONDAY);
        } elseif (preg_match('/\b(aujourd\'?hui|ce soir|tonight|today)\b/', $text)) {
            $start = $today->copy();
        }

        return [
            'date_arrivee' => $start?->toDateString(),
            'nuits' => $nights,
        ];
    }
}
