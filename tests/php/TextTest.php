<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function test_first_char_returns_first_character_of_string(): void
    {
        self::assertSame('h', Text::firstChar('hello', '?'));
    }

    public function test_first_char_returns_fallback_for_null(): void
    {
        self::assertSame('*', Text::firstChar(null, '*'));
    }

    public function test_first_char_returns_fallback_for_empty_string(): void
    {
        self::assertSame('*', Text::firstChar('', '*'));
    }

    public function test_first_char_handles_multibyte_input(): void
    {
        self::assertSame('─', Text::firstChar('─────', '?'));
    }

    public function test_display_width_returns_zero_for_empty_string(): void
    {
        self::assertSame(0, Text::displayWidth(''));
    }

    public function test_display_width_counts_ascii_characters(): void
    {
        self::assertSame(5, Text::displayWidth('hello'));
    }

    public function test_display_width_handles_multibyte_characters(): void
    {
        self::assertSame(5, Text::displayWidth('─────'));
    }

    public function test_display_width_counts_wide_cjk_characters_as_two_cells(): void
    {
        self::assertSame(4, Text::displayWidth('日本'));
    }

    public function test_graphemes_returns_empty_list_for_empty_string(): void
    {
        self::assertSame([], Text::graphemes(''));
    }

    public function test_graphemes_splits_ascii_per_byte(): void
    {
        self::assertSame(['a', 'b', 'c'], Text::graphemes('abc'));
    }

    public function test_graphemes_splits_multibyte_per_codepoint(): void
    {
        self::assertSame(['a', '─', 'b'], Text::graphemes('a─b'));
    }

    public function test_codepoint_count_is_zero_for_empty_string(): void
    {
        self::assertSame(0, Text::codepointCount(''));
    }

    public function test_codepoint_count_counts_ascii_bytes(): void
    {
        self::assertSame(3, Text::codepointCount('abc'));
    }

    public function test_codepoint_count_counts_multibyte_characters_once(): void
    {
        self::assertSame(5, Text::codepointCount('héllo'));
        // Wide CJK glyphs are still single codepoints (unlike displayWidth).
        self::assertSame(2, Text::codepointCount('日本'));
    }
}
