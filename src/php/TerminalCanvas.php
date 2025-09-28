<?php

declare(strict_types=1);

namespace PhelCliGui;

use InvalidArgumentException;

/**
 * Utility helpers to build terminal friendly string representations.
 */
final class TerminalCanvas
{
    /**
     * Builds the lines needed to render a box using the provided border style.
     *
     * @return list<string>
     */
    public static function boxLines(
        int $width,
        int $height,
        BorderStyle $style,
        string $fillChar = ' '
    ): array {
        if ($width < 2 || $height < 2) {
            throw new InvalidArgumentException('Box width and height must be at least 2.');
        }

        $horizontal = str_repeat($style->horizontal(), $width - 2);
        $fill = str_repeat(self::firstCharacter($fillChar, ' '), $width - 2);

        $topAndBottom = sprintf('%s%s%s', $style->corner(), $horizontal, $style->corner());
        $bodyLine = sprintf('%s%s%s', $style->vertical(), $fill, $style->vertical());

        $lines = [$topAndBottom];
        if ($height > 2) {
            $lines = array_merge($lines, array_fill(0, $height - 2, $bodyLine));
        }
        $lines[] = $topAndBottom;

        return $lines;
    }

    public static function horizontalLine(int $length, string $char): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Horizontal line length must be at least 1.');
        }

        return str_repeat(self::firstCharacter($char, '-'), $length);
    }

    private static function firstCharacter(string $value, string $fallback): string
    {
        if ($value === '') {
            return $fallback;
        }

        if (function_exists('mb_substr')) {
            $char = mb_substr($value, 0, 1);
        } else {
            $char = substr($value, 0, 1);
        }

        return $char === '' ? $fallback : $char;
    }
}
