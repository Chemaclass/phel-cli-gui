<?php

declare(strict_types=1);

/**
 * Rendering benchmark: measures the full diff-session pipeline plus the
 * paint and diff hot paths in isolation, at a typical and a full-screen
 * terminal size. Run it before and after touching TerminalGui /
 * ScreenBuffer / Text to catch perf regressions:
 *
 *   composer bench
 *
 * For apps chasing the last drop, the same loops run 2-4x faster again
 * under the CLI JIT: php -d opcache.enable_cli=1 -d opcache.jit=tracing ...
 */

use PhelCliGui\ScreenBuffer;
use PhelCliGui\TerminalGui;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__ . '/../vendor/autoload.php';

const FRAMES = 300;

function bench(int $width, int $height): void
{
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $stream = fopen('php://memory', 'rb');
    $gui = TerminalGui::withStream($stream, $output, new Cursor($output), false);
    $gui->addAnsiStyle('hot', '38;5;196');
    $output->fetch();

    // Full pipeline: an animated TUI frame into a diff session each iteration.
    $gui->beginDiff($width, $height);
    $t0 = hrtime(true);
    for ($f = 0; $f < FRAMES; $f++) {
        $gui->clearBuffer();
        $gui->drawBox(0, 0, $width, $height);
        for ($row = 2; $row < $height - 2; $row += 2) {
            $gui->render(2 + ($f % 20), $row, str_repeat('*', 40), 'hot');
            $gui->render(50, $row, 'score: ' . $f, '');
        }
        $gui->present();
        $output->fetch();
    }
    $fullMs = (hrtime(true) - $t0) / 1e6;
    $gui->endDiff();

    // Paint only: raw ScreenBuffer full-screen fills.
    $buffer = new ScreenBuffer($width, $height);
    $line = str_repeat('.', $width);
    $t0 = hrtime(true);
    for ($f = 0; $f < FRAMES; $f++) {
        for ($y = 0; $y < $height; $y++) {
            $buffer->paint(0, $y, $line, null);
        }
    }
    $paintMs = (hrtime(true) - $t0) / 1e6;

    // Diff only: every second row changed against a blank baseline.
    $blank = new ScreenBuffer($width, $height);
    $dirty = new ScreenBuffer($width, $height);
    for ($y = 0; $y < $height; $y += 2) {
        $dirty->paint(0, $y, $line, 'hot');
    }
    $t0 = hrtime(true);
    for ($f = 0; $f < FRAMES; $f++) {
        $dirty->diff($blank);
    }
    $diffMs = (hrtime(true) - $t0) / 1e6;

    printf("%dx%d, %d frames\n", $width, $height, FRAMES);
    printf("full pipeline: %8.2f ms total  %6.3f ms/frame  (%.0f fps)\n", $fullMs, $fullMs / FRAMES, FRAMES / ($fullMs / 1000));
    printf("paint only:    %8.2f ms total  %6.3f ms/frame\n", $paintMs, $paintMs / FRAMES);
    printf("diff only:     %8.2f ms total  %6.3f ms/frame\n", $diffMs, $diffMs / FRAMES);
    TerminalGui::resetInstance();
}

bench(120, 40);   // typical windowed terminal
echo "\n";
bench(240, 70);   // large full-screen terminal
