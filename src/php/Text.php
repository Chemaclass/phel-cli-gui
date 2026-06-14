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

    /**
     * Splits text into individual graphemes (one codepoint per element),
     * the unit a screen-buffer cell stores.
     *
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return function_exists('mb_str_split')
            ? mb_str_split($text)
            : str_split($text);
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
