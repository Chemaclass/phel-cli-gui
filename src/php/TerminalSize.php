<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Terminal;

/**
 * The terminal's current dimensions.
 *
 * Symfony's Terminal is not usable for a long-running TUI on two counts: it
 * memoises its probe in static properties for the life of the process, and it
 * lets COLUMNS/LINES outrank that probe. Shells export those variables and
 * update them on resize for themselves only, so a child process inherits a
 * snapshot that is frozen at launch — exactly the value a resize handler must
 * not report.
 *
 * Precedence here is therefore: an explicit override, then a live measurement
 * of the controlling terminal, then COLUMNS/LINES (which still serve a piped
 * or CI run with no tty), then Symfony's defaults.
 *
 * Measuring forks `stty`, so the result is cached until invalidated;
 * TerminalGui wires SIGWINCH to invalidate it, which keeps a per-frame call in
 * a render loop free.
 */
final class TerminalSize
{
    /** @var array{int, int}|null cached measurement, null when stale */
    private static ?array $measured = null;

    /** @var array{int, int}|null explicit override, null when unset */
    private static ?array $override = null;

    /** Drops the cached measurement; the next read measures the terminal again. */
    public static function invalidate(): void
    {
        self::$measured = null;
    }

    /**
     * Forces the reported size, outranking every other source. Pass null to
     * clear it and resume measuring. Useful when the process drives a pty of a
     * size it already knows — and in tests, which have no terminal to measure.
     */
    public static function override(?int $width, ?int $height): void
    {
        self::$override = ($width === null || $height === null) ? null : [$width, $height];
    }

    /** @return array{int, int} [width, height] */
    public static function get(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }

        return self::$measured ??= self::measure();
    }

    public static function width(): int
    {
        return self::get()[0];
    }

    public static function height(): int
    {
        return self::get()[1];
    }

    /** @return array{int, int} */
    private static function measure(): array
    {
        // `stty size` prints "rows columns" for the controlling terminal, and
        // unlike the environment it reflects the size right now.
        if (function_exists('shell_exec')) {
            $size = @shell_exec('stty size 2>/dev/null');
            if (is_string($size) && preg_match('/^(\d+)\s+(\d+)/', trim($size), $matches) === 1) {
                $rows = (int) $matches[1];
                $columns = (int) $matches[2];
                if ($rows > 0 && $columns > 0) {
                    return [$columns, $rows];
                }
            }
        }

        // No tty to measure (piped output, CI): fall back to the environment,
        // then to Symfony's probe and its 80x50 defaults.
        $terminal = new Terminal();

        return [$terminal->getWidth(), $terminal->getHeight()];
    }
}
