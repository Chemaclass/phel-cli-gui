<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\BorderStyle;
use PHPUnit\Framework\TestCase;

final class BorderStyleTest extends TestCase
{
    public function test_default_style_is_used_when_no_characters_are_provided(): void
    {
        $style = BorderStyle::withChars();

        self::assertSame('-', $style->horizontal());
        self::assertSame('|', $style->vertical());
        self::assertSame('+', $style->corner());
    }

    public function test_custom_characters_are_truncated_to_first_character(): void
    {
        $style = BorderStyle::withChars('==', '||', '++');

        self::assertSame('=', $style->horizontal());
        self::assertSame('|', $style->vertical());
        self::assertSame('+', $style->corner());
    }

    public function test_falls_back_to_defaults_when_values_are_empty_or_null(): void
    {
        $style = BorderStyle::withChars('', null, '');

        self::assertSame('-', $style->horizontal());
        self::assertSame('|', $style->vertical());
        self::assertSame('+', $style->corner());
    }

    public function test_supports_multibyte_characters(): void
    {
        $style = BorderStyle::withChars('─', '│', '┼');

        self::assertSame('─', $style->horizontal());
        self::assertSame('│', $style->vertical());
        self::assertSame('┼', $style->corner());
    }

    public function test_with_chars_shares_one_glyph_across_all_corners(): void
    {
        $style = BorderStyle::withChars('-', '|', '*');

        self::assertSame('*', $style->topLeft());
        self::assertSame('*', $style->topRight());
        self::assertSame('*', $style->bottomLeft());
        self::assertSame('*', $style->bottomRight());
        self::assertSame('*', $style->corner()); // back-compat: corner() == topLeft()
    }

    public function test_with_corners_keeps_each_corner_distinct(): void
    {
        $style = BorderStyle::withCorners('─', '│', '╭', '╮', '╰', '╯');

        self::assertSame('╭', $style->topLeft());
        self::assertSame('╮', $style->topRight());
        self::assertSame('╰', $style->bottomLeft());
        self::assertSame('╯', $style->bottomRight());
        self::assertSame('╭', $style->corner());
    }

    public function test_with_corners_falls_back_missing_glyphs_to_default(): void
    {
        $style = BorderStyle::withCorners('─', '│', '╭', null, '', '╯');

        self::assertSame('╭', $style->topLeft());
        self::assertSame('+', $style->topRight());
        self::assertSame('+', $style->bottomLeft());
        self::assertSame('╯', $style->bottomRight());
    }

    /**
     * Presets are named, not constructed, so they instantiate inside the test
     * body — data providers run before coverage collection starts.
     *
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function presetProvider(): iterable
    {
        yield 'ascii' => ['ascii', '-', '|', ['+', '+', '+', '+']];
        yield 'light' => ['light', '─', '│', ['┌', '┐', '└', '┘']];
        yield 'rounded' => ['rounded', '─', '│', ['╭', '╮', '╰', '╯']];
        yield 'heavy' => ['heavy', '━', '┃', ['┏', '┓', '┗', '┛']];
        yield 'double' => ['double', '═', '║', ['╔', '╗', '╚', '╝']];
    }

    public function test_corner_mirrors_top_left_for_the_single_corner_model(): void
    {
        self::assertSame('+', BorderStyle::ascii()->corner());
        self::assertSame('╭', BorderStyle::rounded()->corner());
    }

    /**
     * @param list<string> $corners [topLeft, topRight, bottomLeft, bottomRight]
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('presetProvider')]
    public function test_presets_expose_their_glyphs(
        string $preset,
        string $horizontal,
        string $vertical,
        array $corners,
    ): void {
        $style = match ($preset) {
            'ascii' => BorderStyle::ascii(),
            'light' => BorderStyle::light(),
            'rounded' => BorderStyle::rounded(),
            'heavy' => BorderStyle::heavy(),
            'double' => BorderStyle::double(),
            default => self::fail("Unknown preset $preset"),
        };

        self::assertSame($horizontal, $style->horizontal());
        self::assertSame($vertical, $style->vertical());
        self::assertSame($corners, [
            $style->topLeft(),
            $style->topRight(),
            $style->bottomLeft(),
            $style->bottomRight(),
        ]);
    }
}
