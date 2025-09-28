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
}
