<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;

/**
 * An output-formatter style backed by a raw ANSI SGR parameter string
 * (e.g. "38;5;196" for xterm-256 red, or "38;2;255;0;0" for truecolor).
 *
 * Symfony's built-in OutputFormatterStyle only speaks 4-bit names and
 * #hex; this lets named styles carry full 256-colour / RGB sequences while
 * still flowing through the existing formatter, so render bounds stay
 * correct (the styled text the user sees never contains the escape bytes).
 */
final class AnsiStyle implements OutputFormatterStyleInterface
{
    public function __construct(private string $sgr)
    {
    }

    public function setForeground(?string $color): void
    {
    }

    public function setBackground(?string $color): void
    {
    }

    public function setOption(string $option): void
    {
    }

    public function unsetOption(string $option): void
    {
    }

    public function setOptions(array $options): void
    {
    }

    public function apply(string $text): string
    {
        if ($this->sgr === '') {
            return $text;
        }

        return "\033[{$this->sgr}m{$text}\033[0m";
    }
}
