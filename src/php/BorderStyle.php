<?php

declare(strict_types=1);

namespace PhelCliGui;

final class BorderStyle
{
    private const DEFAULT_HORIZONTAL = '-';
    private const DEFAULT_VERTICAL = '|';
    private const DEFAULT_CORNER = '+';

    public function __construct(
        private ?string $horizontal = self::DEFAULT_HORIZONTAL,
        private ?string $vertical = self::DEFAULT_VERTICAL,
        private ?string $corner = self::DEFAULT_CORNER
    ) {
    }

    public function horizontal(): string
    {
        return $this->horizontal ?: self::DEFAULT_HORIZONTAL;
    }

    public function vertical(): string
    {
        return $this->vertical ?: self::DEFAULT_VERTICAL;
    }

    public function corner(): string
    {
        return $this->corner ?: self::DEFAULT_CORNER;
    }
}
