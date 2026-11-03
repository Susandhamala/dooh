<?php

namespace App\Support;

/** Rupees as decimal strings at the edges, whole paisa (integers) inside. */
final class Money
{
    public static function toPaisa(string|int|float $rupees): int
    {
        return (int) round((float) $rupees * 100);
    }

    /** "1234.50" */
    public static function toRupees(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $abs = abs($paisa);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
