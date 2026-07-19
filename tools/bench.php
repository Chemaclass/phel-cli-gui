<?php

declare(strict_types=1);

/**
 * Rendering benchmark: measures the full diff-session pipeline plus the
 * paint and diff hot paths in isolation. Run it before and after touching
 * TerminalGui / ScreenBuffer / Text to catch perf regressions:
 *
 *   composer bench
 */

use PhelCliGui\ScreenBuffer;
use PhelCliGui\TerminalGui;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__ . '/../vendor/autoload.php';

const WIDTH = 120;
const HEIGHT = 40;
const FRAMES = 300;

$output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
$stream = fopen('php://memory', 'rb');
$gui = TerminalGui::withStream($stream, $output, new Cursor($output), false);
$gui->addAnsiStyle('hot', '38;5;196');
$output->fetch();

// Full pipeline: an animated TUI frame into a diff session each iteration.
$gui->beginDiff(WIDTH, HEIGHT);
$t0 = hrtime(true);
for ($f = 0; $f < FRAMES; $f++) {
    $gui->clearBuffer();
    $gui->drawBox(0, 0, WIDTH, HEIGHT);
    for ($row = 2; $row < HEIGHT - 2; $row += 2) {
        $gui->render(2 + ($f % 20), $row, str_repeat('*', 40), 'hot');
        $gui->render(50, $row, 'score: ' . $f, '');
    }
    $gui->present();
    $output->fetch();
}
$fullMs = (hrtime(true) - $t0) / 1e6;
$gui->endDiff();

// Paint only: raw ScreenBuffer full-screen fills.
$buffer = new ScreenBuffer(WIDTH, HEIGHT);
$line = str_repeat('.', WIDTH);
$t0 = hrtime(true);
for ($f = 0; $f < FRAMES; $f++) {
    for ($y = 0; $y < HEIGHT; $y++) {
        $buffer->paint(0, $y, $line, null);
    }
}
$paintMs = (hrtime(true) - $t0) / 1e6;

// Diff only: every second row changed against a blank baseline.
$blank = new ScreenBuffer(WIDTH, HEIGHT);
$dirty = new ScreenBuffer(WIDTH, HEIGHT);
for ($y = 0; $y < HEIGHT; $y += 2) {
    $dirty->paint(0, $y, $line, 'hot');
}
$t0 = hrtime(true);
for ($f = 0; $f < FRAMES; $f++) {
    $dirty->diff($blank);
}
$diffMs = (hrtime(true) - $t0) / 1e6;

printf("%dx%d, %d frames\n", WIDTH, HEIGHT, FRAMES);
printf("full pipeline: %8.2f ms total  %6.3f ms/frame  (%.0f fps)\n", $fullMs, $fullMs / FRAMES, FRAMES / ($fullMs / 1000));
printf("paint only:    %8.2f ms total  %6.3f ms/frame\n", $paintMs, $paintMs / FRAMES);
printf("diff only:     %8.2f ms total  %6.3f ms/frame\n", $diffMs, $diffMs / FRAMES);
TerminalGui::resetInstance();
