<?php

namespace Gabrieldev\Anaf;

final class Cui
{
    /** Strips spaces and the optional "RO" prefix: " ro 45845765 " -> "45845765". */
    public static function normalize(mixed $value): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');

        return str_starts_with($value, 'RO') ? substr($value, 2) : $value;
    }

    /** Checks length and the official control digit (key 753217532). */
    public static function isValid(mixed $value): bool
    {
        $cui = self::normalize($value);
        if (! preg_match('/^[1-9]\d{1,9}$/', $cui)) {
            return false;
        }

        $digits = str_pad(substr($cui, 0, -1), 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split('753217532') as $i => $weight) {
            $sum += (int) $digits[$i] * (int) $weight;
        }
        $control = ($sum * 10) % 11;

        return ($control === 10 ? 0 : $control) === (int) substr($cui, -1);
    }
}
