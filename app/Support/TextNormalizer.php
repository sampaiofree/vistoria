<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TextNormalizer
{
    public static function text(string $value): string
    {
        return (string) Str::of($value)
            ->trim()
            ->replaceMatches('/\s+/u', ' ');
    }

    public static function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::text($value);

        return $normalized === '' ? null : $normalized;
    }

    public static function document(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $value) ?? '';

        return $normalized === '' ? null : $normalized;
    }

    public static function technicalCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) Str::of($value)
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/u', '');

        return $normalized === '' ? null : $normalized;
    }

    public static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }
}
