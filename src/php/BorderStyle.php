<?php

declare(strict_types=1);

namespace PhelCliGui;

final class BorderStyle
{
    private const string DEFAULT_HORIZONTAL = '-';
    private const string DEFAULT_VERTICAL = '|';
    private const string DEFAULT_CORNER = '+';

    private function __construct(
        private readonly string $horizontal,
        private readonly string $vertical,
        private readonly string $corner,
    ) {
    }

    public static function withChars(
        ?string $horizontal = self::DEFAULT_HORIZONTAL,
        ?string $vertical = self::DEFAULT_VERTICAL,
        ?string $corner = self::DEFAULT_CORNER,
    ): self {
        return new self(
            Text::firstChar($horizontal, self::DEFAULT_HORIZONTAL),
            Text::firstChar($vertical, self::DEFAULT_VERTICAL),
            Text::firstChar($corner, self::DEFAULT_CORNER),
        );
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
