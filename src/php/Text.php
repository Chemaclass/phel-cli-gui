<?php

declare(strict_types=1);

namespace PhelCliGui;

final class Text
{
    public static function firstChar(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $char = function_exists('mb_substr')
            ? mb_substr($value, 0, 1)
            : substr($value, 0, 1);

        return $char === '' ? $fallback : $char;
    }

    public static function displayWidth(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (function_exists('mb_strwidth')) {
            return mb_strwidth($text);
        }

        if (function_exists('mb_strlen')) {
            return mb_strlen($text);
        }

        return strlen($text);
    }
}
