<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use InvalidArgumentException;
use PhelCliGui\BorderStyle;
use PhelCliGui\TerminalGui;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TerminalGuiTest extends TestCase
{
    private BufferedOutput $output;

    /** @var resource */
    private $inputStream;

    private TerminalGui $gui;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $this->inputStream = fopen('php://memory', 'rb');

        $this->gui = TerminalGui::withStream(
            inputStream: $this->inputStream,
            output: $this->output,
            cursor: new Cursor($this->output),
            registerShutdownHandlers: false,
        );

        // Drain the init output (cursor hide + move-to-origin).
        $this->output->fetch();
    }

    protected function tearDown(): void
    {
        TerminalGui::resetInstance();
        if (is_resource($this->inputStream)) {
            fclose($this->inputStream);
        }
    }

    public function test_fill_region_writes_one_row_per_line_with_fill_char(): void
    {
        $this->gui->fillRegion(0, 0, 4, 3, '.');

        self::assertSame(3, substr_count($this->output->fetch(), '....'));
    }

    public function test_fill_region_updates_max_bounds(): void
    {
        $this->gui->fillRegion(2, 1, 5, 3, '#');

        self::assertSame(2 + 5 - 1, $this->gui->getMaxWidth());
        self::assertSame(1 + 3 - 1, $this->gui->getMaxHeight());
    }

    public function test_fill_region_rejects_zero_or_negative_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Region width and height must be at least 1.');

        $this->gui->fillRegion(0, 0, 0, 1);
    }

    public function test_hide_cursor_emits_ansi_hide_sequence(): void
    {
        $this->gui->hideCursor();

        self::assertStringContainsString("\033[?25l", $this->output->fetch());
    }

    public function test_show_cursor_emits_ansi_show_sequence(): void
    {
        $this->gui->showCursor();

        self::assertStringContainsString("\033[?25h", $this->output->fetch());
    }

    public function test_render_writes_text_at_requested_position(): void
    {
        $this->gui->render(3, 2, 'hello');

        $content = $this->output->fetch();

        // Symfony Cursor::moveToPosition(col, row) emits "\e[{row+1};{col}H".
        self::assertStringContainsString("\033[3;3H", $content);
        self::assertStringContainsString('hello', $content);
    }

    public function test_render_tracks_max_bounds_using_display_width(): void
    {
        $this->gui->render(0, 0, 'abc');
        self::assertSame(2, $this->gui->getMaxWidth());
        self::assertSame(0, $this->gui->getMaxHeight());

        $this->gui->render(10, 5, 'x');
        self::assertSame(10, $this->gui->getMaxWidth());
        self::assertSame(5, $this->gui->getMaxHeight());
    }

    public function test_render_text_block_splits_on_newlines(): void
    {
        $this->gui->renderTextBlock(0, 0, "line-1\nline-2\nline-3");

        $content = $this->output->fetch();
        self::assertStringContainsString('line-1', $content);
        self::assertStringContainsString('line-2', $content);
        self::assertStringContainsString('line-3', $content);
        self::assertSame(2, $this->gui->getMaxHeight());
    }

    public function test_draw_vertical_line_rejects_zero_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->gui->drawVerticalLine(0, 0, 0, '|');
    }

    public function test_draw_vertical_line_updates_bounds_for_column(): void
    {
        $this->gui->drawVerticalLine(4, 0, 6, '|');

        self::assertSame(4, $this->gui->getMaxWidth());
        self::assertSame(5, $this->gui->getMaxHeight());
    }

    public function test_render_board_uses_default_border_style_at_origin(): void
    {
        $this->gui->renderBoard(4, 3);

        $content = $this->output->fetch();
        self::assertStringContainsString('+--+', $content);
        self::assertStringContainsString('|  |', $content);
    }

    public function test_render_board_accepts_custom_border_style(): void
    {
        $this->gui->renderBoard(3, 2, BorderStyle::withChars('=', '!', '*'));

        $content = $this->output->fetch();
        self::assertStringContainsString('*=*', $content);
    }

    public function test_draw_box_writes_fill_inside_borders(): void
    {
        $this->gui->drawBox(0, 0, 5, 4, BorderStyle::withChars('-', '|', '+'), '.');

        $content = $this->output->fetch();
        self::assertStringContainsString('+---+', $content);
        self::assertStringContainsString('|...|', $content);
        self::assertSame(4, $this->gui->getMaxWidth());
        self::assertSame(3, $this->gui->getMaxHeight());
    }

    public function test_draw_horizontal_line_writes_repeating_character(): void
    {
        $this->gui->drawHorizontalLine(1, 2, 6, '-');

        $content = $this->output->fetch();
        self::assertStringContainsString('------', $content);
        self::assertSame(1 + 6 - 1, $this->gui->getMaxWidth());
        self::assertSame(2, $this->gui->getMaxHeight());
    }

    public function test_clear_screen_emits_ansi_clear_sequence(): void
    {
        $this->gui->clearScreen();

        self::assertStringContainsString("\033[2J", $this->output->fetch());
    }

    public function test_clear_output_emits_ansi_clear_output_sequence(): void
    {
        $this->gui->clearOutput();

        self::assertStringContainsString("\033[0J", $this->output->fetch());
    }

    public function test_clear_line_moves_to_row_and_clears(): void
    {
        $this->gui->clearLine(3);

        $content = $this->output->fetch();
        self::assertStringContainsString("\033[4;0H", $content);
        self::assertStringContainsString("\033[2K", $content);
    }

    public function test_add_output_formatter_applies_style_to_named_tag(): void
    {
        $this->gui->addOutputFormatter('danger', new OutputFormatterStyle('red', null, ['bold']));
        $this->gui->render(0, 0, 'boom', 'danger');

        $content = $this->output->fetch();
        self::assertStringContainsString("\033[31;1mboom\033[39;22m", $content);
    }

    public function test_render_without_style_writes_plain_text(): void
    {
        $this->gui->render(0, 0, 'plain', '');

        $content = $this->output->fetch();
        self::assertStringContainsString('plain', $content);
        self::assertStringNotContainsString("<", $content);
    }

    public function test_get_instance_returns_shared_singleton(): void
    {
        TerminalGui::resetInstance();

        $a = TerminalGui::getInstance(
            $this->inputStream,
            $this->output,
            new Cursor($this->output),
            false,
        );
        $b = TerminalGui::getInstance($this->inputStream);

        self::assertSame($a, $b);
    }
}
