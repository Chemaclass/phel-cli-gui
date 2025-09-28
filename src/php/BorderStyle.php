<?php

declare(strict_types=1);

namespace PhelCliGui;

final class BorderStyle
{
    private const DEFAULT_HORIZONTAL = '-';
    private const DEFAULT_VERTICAL = '|';
    private const DEFAULT_CORNER = '+';

    private function __construct(
        private string $horizontal,
        private string $vertical,
        private string $corner,
    ) {
    }

    public static function withChars(
        ?string $horizontal = self::DEFAULT_HORIZONTAL,
        ?string $vertical = self::DEFAULT_VERTICAL,
        ?string $corner = self::DEFAULT_CORNER
    ) {
        $horizontal = self::firstCharacter($horizontal, self::DEFAULT_HORIZONTAL);
        $vertical = self::firstCharacter($vertical, self::DEFAULT_VERTICAL);
        $corner = self::firstCharacter($corner, self::DEFAULT_CORNER);

        return new self($horizontal, $vertical, $corner);
    }

    private static function firstCharacter(?string $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (function_exists('mb_substr')) {
            $char = mb_substr($value, 0, 1);
        } else {
            $char = substr($value, 0, 1);
        }

        return $char === '' ? $fallback : $char;
    }

    public function horizontal(): string
    {
        return $this->horizontal;
    }

    public function vertical(): string
    {
        return $this->vertical;
    }

    public function corner(): string
    {
        return $this->corner;
    }
}
