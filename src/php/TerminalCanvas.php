<?php

declare(strict_types=1);

namespace PhelCliGui;

use InvalidArgumentException;

final class TerminalCanvas
{
    /**
     * @return list<string>
     */
    public static function boxLines(
        int $width,
        int $height,
        BorderStyle $style,
        string $fillChar = ' ',
    ): array {
        if ($width < 2 || $height < 2) {
            throw new InvalidArgumentException('Box width and height must be at least 2.');
        }

        $horizontal = str_repeat($style->horizontal(), $width - 2);
        $fill = str_repeat(Text::firstChar($fillChar, ' '), $width - 2);

        $topAndBottom = $style->corner() . $horizontal . $style->corner();
        $bodyLine = $style->vertical() . $fill . $style->vertical();

        $lines = [$topAndBottom];
        if ($height > 2) {
            array_push($lines, ...array_fill(0, $height - 2, $bodyLine));
        }
        $lines[] = $topAndBottom;

        return $lines;
    }

    public static function horizontalLine(int $length, string $char): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Horizontal line length must be at least 1.');
        }

        return str_repeat(Text::firstChar($char, '-'), $length);
    }
}
