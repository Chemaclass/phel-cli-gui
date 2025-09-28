<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use InvalidArgumentException;
use PhelCliGui\BorderStyle;
use PhelCliGui\TerminalCanvas;
use PHPUnit\Framework\TestCase;

final class TerminalCanvasTest extends TestCase
{
    public function test_box_lines_are_built_using_border_style_characters(): void
    {
        $style = BorderStyle::withChars('-', '|', '+');

        $lines = TerminalCanvas::boxLines(4, 3, $style);

        self::assertSame([
            '+--+',
            '|  |',
            '+--+',
        ], $lines);
    }

    public function test_box_lines_use_fill_character_and_handle_height_of_two(): void
    {
        $style = BorderStyle::withChars('#', '!', '*');

        $lines = TerminalCanvas::boxLines(5, 2, $style, '@@');

        self::assertSame([
            '*###*',
            '*###*',
        ], $lines);
    }

    public function test_box_lines_fill_body_with_first_character_of_fill_string(): void
    {
        $style = BorderStyle::withChars('-', '|', '+');

        $lines = TerminalCanvas::boxLines(5, 4, $style, '@#');

        self::assertSame('+---+', $lines[0]);
        self::assertSame('|@@@|', $lines[1]);
        self::assertSame('|@@@|', $lines[2]);
        self::assertSame('+---+', $lines[3]);
    }

    public function test_box_lines_require_minimum_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Box width and height must be at least 2.');

        TerminalCanvas::boxLines(1, 1, BorderStyle::withChars());
    }

    public function test_horizontal_line_repeats_first_character(): void
    {
        $line = TerminalCanvas::horizontalLine(4, 'abc');

        self::assertSame('aaaa', $line);
    }

    public function test_horizontal_line_requires_positive_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Horizontal line length must be at least 1.');

        TerminalCanvas::horizontalLine(0, '-');
    }
}
