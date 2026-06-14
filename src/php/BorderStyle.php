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
        private readonly string $topLeft,
        private readonly string $topRight,
        private readonly string $bottomLeft,
        private readonly string $bottomRight,
    ) {
    }

    /**
     * A border with one corner glyph shared by all four corners — the ASCII
     * default (`+`) or any single character you pass.
     */
    public static function withChars(
        ?string $horizontal = self::DEFAULT_HORIZONTAL,
        ?string $vertical = self::DEFAULT_VERTICAL,
        ?string $corner = self::DEFAULT_CORNER,
    ): self {
        return self::withCorners($horizontal, $vertical, $corner, $corner, $corner, $corner);
    }

    /**
     * A border with distinct corner glyphs, the shape proper box-drawing needs
     * (e.g. `╭ ╮ ╰ ╯`). Each missing glyph falls back to the ASCII default.
     */
    public static function withCorners(
        ?string $horizontal = self::DEFAULT_HORIZONTAL,
        ?string $vertical = self::DEFAULT_VERTICAL,
        ?string $topLeft = self::DEFAULT_CORNER,
        ?string $topRight = self::DEFAULT_CORNER,
        ?string $bottomLeft = self::DEFAULT_CORNER,
        ?string $bottomRight = self::DEFAULT_CORNER,
    ): self {
        return new self(
            Text::firstChar($horizontal, self::DEFAULT_HORIZONTAL),
            Text::firstChar($vertical, self::DEFAULT_VERTICAL),
            Text::firstChar($topLeft, self::DEFAULT_CORNER),
            Text::firstChar($topRight, self::DEFAULT_CORNER),
            Text::firstChar($bottomLeft, self::DEFAULT_CORNER),
            Text::firstChar($bottomRight, self::DEFAULT_CORNER),
        );
    }

    /** Single-line ASCII box: `- | + + + +`. The library default. */
    public static function ascii(): self
    {
        return self::withChars();
    }

    /** Single-line Unicode box: `─ │ ┌ ┐ └ ┘`. */
    public static function light(): self
    {
        return self::withCorners('─', '│', '┌', '┐', '└', '┘');
    }

    /** Single-line Unicode box with rounded corners: `─ │ ╭ ╮ ╰ ╯`. */
    public static function rounded(): self
    {
        return self::withCorners('─', '│', '╭', '╮', '╰', '╯');
    }

    /** Heavy Unicode box: `━ ┃ ┏ ┓ ┗ ┛`. */
    public static function heavy(): self
    {
        return self::withCorners('━', '┃', '┏', '┓', '┗', '┛');
    }

    /** Double-line Unicode box: `═ ║ ╔ ╗ ╚ ╝`. */
    public static function double(): self
    {
        return self::withCorners('═', '║', '╔', '╗', '╚', '╝');
    }

    public function horizontal(): string
    {
        return $this->horizontal;
    }

    public function vertical(): string
    {
        return $this->vertical;
    }

    /** The top-left corner. Kept for the single-corner (`withChars`) model. */
    public function corner(): string
    {
        return $this->topLeft;
    }

    public function topLeft(): string
    {
        return $this->topLeft;
    }

    public function topRight(): string
    {
        return $this->topRight;
    }

    public function bottomLeft(): string
    {
        return $this->bottomLeft;
    }

    public function bottomRight(): string
    {
        return $this->bottomRight;
    }
}
