<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Turns cents into the one string a member reads.
 *
 * Formatting happens here and nowhere else, at display, once. Maveren formatted
 * in eleven places with four different results — number_format in some, string
 * concatenation in others, and a JavaScript toFixed on the dashboard that
 * disagreed with the server by a cent on numbers ending in 5.
 *
 * The arithmetic is integer and string only. number_format() would be the
 * obvious way to group the thousands and it takes a float, which puts the whole
 * value through the representation this class exists to avoid: above 2^53 the
 * grouping would be applied to digits that are already wrong. So the integer
 * part is split into groups of three by reversing the digit string, which is
 * exact at any magnitude a BIGINT can hold.
 */
final class Usd
{
    public const SYMBOL = '$';

    public static function format(Cents $cents): string
    {
        // Via the decimal string rather than abs(): abs(PHP_INT_MIN) returns a
        // float, so the one input that most needs exactness is the one input
        // that would lose it.
        $digits = (string) $cents->toInt();
        $sign = str_starts_with($digits, '-') ? '-' : '';
        $digits = ltrim($digits, '-');
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);

        $major = substr($digits, 0, -2);
        $minor = substr($digits, -2);

        $grouped = strrev(implode(',', str_split(strrev($major), 3)));

        return $sign.$grouped.'.'.$minor;
    }

    public static function formatWithSymbol(Cents $cents): string
    {
        // The sign goes outside the symbol: -$5.00 reads as a debit, $-5.00
        // reads as a typo.
        $formatted = self::format($cents);

        return str_starts_with($formatted, '-')
            ? '-'.self::SYMBOL.substr($formatted, 1)
            : self::SYMBOL.$formatted;
    }
}
