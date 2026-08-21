<?php

namespace App\Support;

/**
 * Amounts are stored in paise, the way a payment gateway stores them.
 * This class exists so that no other class has to remember that.
 */
final class Money
{
    public static function inr(int $paise): string
    {
        $rupees = intdiv($paise, 100);
        $remainder = $paise % 100;

        $formatted = '₹'.number_format($rupees);

        if ($remainder === 0) {
            return $formatted;
        }

        return $formatted.'.'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);
    }
}
