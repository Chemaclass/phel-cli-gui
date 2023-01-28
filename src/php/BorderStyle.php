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
        $horizontal = ($horizontal === null) ? self::DEFAULT_HORIZONTAL : $horizontal[0];
        $vertical = ($vertical === null) ? self::DEFAULT_VERTICAL : $vertical[0];
        $corner = ($corner === null) ? self::DEFAULT_CORNER : $corner[0];

        return new self($horizontal, $vertical, $corner);
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
