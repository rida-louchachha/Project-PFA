<?php

namespace App\Support;

/**
 * Display label for a stored currency code: the configured currency (MAD by default)
 * is shown with its display symbol (CURRENCY_SYMBOL, "DH" by default); any other code
 * is shown as-is.
 */
final class Currency
{
    public static function label(?string $code = null): string
    {
        $default = (string) config('travelplan.currency.code', 'MAD');
        $code = $code ?: $default;

        return strtoupper($code) === strtoupper($default)
            ? (string) config('travelplan.currency.symbol', $default)
            : $code;
    }
}
