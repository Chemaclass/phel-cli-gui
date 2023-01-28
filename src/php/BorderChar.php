<?php

declare(strict_types=1);

namespace PhelCliGui;

final class BorderChar
{
    public function __construct(
        private string $horizontal = '-',
        private string $vertical = '|',
        private string $corner = '+'
    ) {
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
