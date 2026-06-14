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
     * @return iterable<string, array{BorderStyle, string, string, list<string>}>
     */
    public static function presetProvider(): iterable
    {
        yield 'ascii' => [BorderStyle::ascii(), '-', '|', ['+', '+', '+', '+']];
        yield 'light' => [BorderStyle::light(), '─', '│', ['┌', '┐', '└', '┘']];
        yield 'rounded' => [BorderStyle::rounded(), '─', '│', ['╭', '╮', '╰', '╯']];
        yield 'heavy' => [BorderStyle::heavy(), '━', '┃', ['┏', '┓', '┗', '┛']];
        yield 'double' => [BorderStyle::double(), '═', '║', ['╔', '╗', '╚', '╝']];
    }

    /**
     * @param list<string> $corners [topLeft, topRight, bottomLeft, bottomRight]
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('presetProvider')]
    public function test_presets_expose_their_glyphs(
        BorderStyle $style,
        string $horizontal,
        string $vertical,
        array $corners,
    ): void {
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
