<?php

namespace App\Services;

/**
 * Converts between ringgit text typed by people and the sen integers the database stores.
 */
class Money
{
    /**
     * "50", "50.5" or "1,234.00" become sen. Returns null when the text is not a positive amount.
     */
    public static function toCents(string $amount): ?int
    {
        $clean = str_replace(',', '', trim($amount));

        if (! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        $cents = (int) round(((float) $clean) * 100);

        return $cents > 0 ? $cents : null;
    }

    /**
     * 123450 becomes "RM 1,234.50".
     */
    public static function format(int $cents, string $symbol = 'RM'): string
    {
        return $symbol.' '.number_format($cents / 100, 2, '.', ',');
    }
}
