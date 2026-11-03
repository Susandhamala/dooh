<?php

namespace App\Support;

/**
 * Nepali mobile numbers: 10 digits starting 96, 97 or 98 (NTC, Ncell, Smart), with or without
 * +977 / 977 / 0 in front and with spaces or dashes. Stored as +977XXXXXXXXXX.
 */
final class NepaliPhone
{
    public static function normalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $digits = preg_replace('/[\s\-().]/', '', $input) ?? '';
        $digits = preg_replace('/^(\+?977|0)/', '', $digits) ?? '';

        return preg_match('/^9[678]\d{8}$/', $digits) === 1 ? '+977'.$digits : null;
    }

    /** +977 98•••••678 */
    public static function mask(string $e164): string
    {
        return substr($e164, 0, 6).str_repeat('•', 5).substr($e164, -3);
    }
}
