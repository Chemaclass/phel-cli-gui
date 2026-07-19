<?php

declare(strict_types=1);

namespace PhelCliGui;

final class Text
{
    /**
     * Printable ASCII. Text made only of these bytes has one single-byte glyph
     * per cell, so byte operations (str_split, strlen, $s[0]) are exact and the
     * multibyte machinery can be skipped entirely — the common case for borders,
     * fills, and latin UI text on every hot render path.
     */
    private const ASCII_PRINTABLE = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~";

    private static ?bool $hasMbSubstr = null;
    private static ?bool $hasMbStrSplit = null;
    private static ?bool $hasMbStrlen = null;
    private static ?bool $hasMbStrwidth = null;

    public static function firstChar(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (ord($value[0]) < 0x80) {
            return $value[0];
        }

        // $value is non-empty, so the first character is always non-empty too.
        return (self::$hasMbSubstr ??= function_exists('mb_substr'))
            ? mb_substr($value, 0, 1)
            : substr($value, 0, 1);
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

        if (strspn($text, self::ASCII_PRINTABLE) === strlen($text)) {
            return str_split($text);
        }

        return (self::$hasMbStrSplit ??= function_exists('mb_str_split'))
            ? mb_str_split($text)
            : str_split($text);
    }

    /** Number of codepoints — the cells a run of text advances the cursor. */
    public static function codepointCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (strspn($text, self::ASCII_PRINTABLE) === strlen($text)) {
            return strlen($text);
        }

        return (self::$hasMbStrlen ??= function_exists('mb_strlen'))
            ? mb_strlen($text)
            : strlen($text);
    }

    public static function displayWidth(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (strspn($text, self::ASCII_PRINTABLE) === strlen($text)) {
            return strlen($text);
        }

        if (self::$hasMbStrwidth ??= function_exists('mb_strwidth')) {
            return mb_strwidth($text);
        }

        return self::codepointCount($text);
    }
}
