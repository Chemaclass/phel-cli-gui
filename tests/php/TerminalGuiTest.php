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

    public function test_unstyled_text_keeps_literal_angle_brackets(): void
    {
        // Without a style the text is written raw, so markup-looking content is
        // emitted verbatim instead of being parsed (and swallowed) as a tag.
        $this->gui->render(0, 0, '<not-a-tag>');

        self::assertStringContainsString('<not-a-tag>', $this->output->fetch());
    }

    public function test_unstyled_diff_run_keeps_literal_angle_brackets(): void
    {
        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, '<x>');
        $this->gui->present();

        self::assertStringContainsString('<x>', $this->output->fetch());
        $this->gui->endDiff();
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

    public function test_begin_frame_defers_writes_until_end_frame(): void
    {
        $this->gui->beginFrame();
        $this->gui->render(0, 0, 'buffered');

        // Nothing reaches the real output while the frame is open.
        self::assertSame('', $this->output->fetch());

        $this->gui->endFrame();
        self::assertStringContainsString('buffered', $this->output->fetch());
    }

    public function test_frame_output_matches_immediate_mode(): void
    {
        $this->gui->render(1, 1, 'abc');
        $immediate = $this->output->fetch();

        $this->gui->beginFrame();
        $this->gui->render(1, 1, 'abc');
        $this->gui->endFrame();
        $buffered = $this->output->fetch();

        self::assertSame($immediate, $buffered);
    }

    public function test_nested_frames_flush_only_on_outermost_end(): void
    {
        $this->gui->beginFrame();
        $this->gui->beginFrame();
        $this->gui->render(0, 0, 'x');

        $this->gui->endFrame(); // inner — no flush yet
        self::assertSame('', $this->output->fetch());

        $this->gui->endFrame(); // outer — flush
        self::assertStringContainsString('x', $this->output->fetch());
    }

    public function test_end_frame_without_begin_is_noop(): void
    {
        $this->gui->endFrame();

        self::assertSame('', $this->output->fetch());
    }

    public function test_frame_collapses_many_draws_into_a_single_write(): void
    {
        TerminalGui::resetInstance();

        $counter = new class(OutputInterface::VERBOSITY_NORMAL, true) extends BufferedOutput {
            public int $writes = 0;

            protected function doWrite(string $message, bool $newline): void
            {
                ++$this->writes;
                parent::doWrite($message, $newline);
            }
        };

        $gui = TerminalGui::withStream(
            inputStream: $this->inputStream,
            output: $counter,
            cursor: new Cursor($counter),
            registerShutdownHandlers: false,
        );
        $counter->writes = 0; // ignore init (cursor hide + move-to-origin)

        $gui->beginFrame();
        for ($row = 0; $row < 10; ++$row) {
            $gui->render(0, $row, 'row');
        }
        $gui->endFrame();

        // 10 immediate renders would be 30+ writes; buffered collapses to one.
        self::assertSame(1, $counter->writes);
    }

    public function test_frame_coalesces_cursor_finalization_to_one_move(): void
    {
        // Two draws at distinct rows. In immediate mode each draw parks the
        // cursor at the running max-bounds; inside a frame those intermediate
        // parks are wasted bytes — only the final position matters.
        $this->gui->beginFrame();
        $this->gui->render(0, 0, 'ab'); // max-bounds (1, 0)
        $this->gui->render(0, 5, 'cd'); // max-bounds (1, 5)
        $this->gui->endFrame();

        $content = $this->output->fetch();

        // The intermediate park after the first draw — moveToPosition(1, 0) =>
        // "\e[1;1H" — must not appear; it is coalesced away.
        self::assertSame(0, substr_count($content, "\033[1;1H"));

        // Exactly one trailing park to the final max-bounds (1, 5) => "\e[6;1H".
        self::assertSame(1, substr_count($content, "\033[6;1H"));
    }

    public function test_named_style_renders_inside_frame(): void
    {
        $this->gui->addOutputFormatter('danger', new OutputFormatterStyle('red', null, ['bold']));

        $this->gui->beginFrame();
        $this->gui->render(0, 0, 'boom', 'danger');
        $this->gui->endFrame();

        self::assertStringContainsString("\033[31;1mboom\033[39;22m", $this->output->fetch());
    }

    public function test_add_ansi_style_renders_256_colour_sequence(): void
    {
        $this->gui->addAnsiStyle('lava', '38;5;196;48;5;52');
        $this->gui->render(0, 0, 'x', 'lava');

        // Text stays clean; the escape wraps it and bounds use the clean text.
        self::assertStringContainsString("\033[38;5;196;48;5;52mx\033[0m", $this->output->fetch());
        self::assertSame(0, $this->gui->getMaxWidth());
    }

    public function test_enter_alt_screen_emits_sequence_once(): void
    {
        $this->gui->enterAltScreen();
        $this->gui->enterAltScreen(); // idempotent

        self::assertSame(1, substr_count($this->output->fetch(), "\033[?1049h"));
    }

    public function test_leave_alt_screen_emits_restore_sequence(): void
    {
        $this->gui->enterAltScreen();
        $this->output->fetch();

        $this->gui->leaveAltScreen();
        self::assertStringContainsString("\033[?1049l", $this->output->fetch());
    }

    public function test_leave_alt_screen_without_enter_is_noop(): void
    {
        $this->gui->leaveAltScreen();

        self::assertStringNotContainsString("\033[?1049l", $this->output->fetch());
    }

    public function test_cleanup_leaves_alt_screen(): void
    {
        // cleanUp only runs on the registered singleton, so build one.
        TerminalGui::resetInstance();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $gui = TerminalGui::getInstance($this->inputStream, $output, new Cursor($output), false);
        $output->fetch(); // drain init

        $gui->enterAltScreen();
        $output->fetch();

        TerminalGui::resetInstance(); // triggers cleanUp on the singleton
        self::assertStringContainsString("\033[?1049l", $output->fetch());
    }

    public function test_diff_present_writes_painted_cells_at_position(): void
    {
        $this->gui->beginDiff(20, 5);
        $this->gui->render(3, 2, 'hello');
        self::assertSame('', $this->output->fetch()); // nothing until present

        $this->gui->present();
        $content = $this->output->fetch();

        // moveToPosition(3, 2) => "\e[3;3H" then the run text.
        self::assertStringContainsString("\033[3;3Hhello", $content);
        $this->gui->endDiff();
    }

    public function test_diff_present_is_noop_when_nothing_changed(): void
    {
        $this->gui->beginDiff(20, 5);
        $this->gui->render(0, 0, 'static');
        $this->gui->present();
        $this->output->fetch(); // drain first frame

        // Same content again: the diff is empty, so present writes nothing.
        $this->gui->render(0, 0, 'static');
        $this->gui->present();

        self::assertSame('', $this->output->fetch());
        $this->gui->endDiff();
    }

    public function test_diff_present_writes_only_changed_run(): void
    {
        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, 'hello');
        $this->gui->present();
        $this->output->fetch(); // drain first frame

        // Flip one cell: "hello" -> "heXlo". Only that cell repaints.
        $this->gui->render(2, 0, 'X');
        $this->gui->present();
        $content = $this->output->fetch();

        self::assertStringContainsString("\033[1;2HX", $content);
        self::assertStringNotContainsString('hello', $content);
        $this->gui->endDiff();
    }

    public function test_clear_buffer_repaints_from_blank(): void
    {
        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, 'abc', 'lava');
        $this->gui->addAnsiStyle('lava', '38;5;196');
        $this->gui->present();
        $this->output->fetch();

        // Clear then leave the cell blank: the old glyphs must be overwritten
        // with spaces in the diff.
        $this->gui->clearBuffer();
        $this->gui->present();
        $content = $this->output->fetch();

        self::assertStringContainsString("\033[1;0H", $content); // repaint at origin
        self::assertStringContainsString('   ', $content);       // blanked cells
        $this->gui->endDiff();
    }

    public function test_diff_styled_run_wraps_in_named_style(): void
    {
        $this->gui->addAnsiStyle('lava', '38;5;196');
        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, 'x', 'lava');
        $this->gui->present();

        self::assertStringContainsString("\033[38;5;196mx\033[0m", $this->output->fetch());
        $this->gui->endDiff();
    }

    public function test_diff_adjacent_runs_emit_no_move_between_them(): void
    {
        $this->gui->addAnsiStyle('red', '38;5;1');
        $this->gui->addAnsiStyle('blue', '38;5;4');

        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, 'ab', 'red');  // run at x=0, ends at x=2
        $this->gui->render(2, 0, 'cd', 'blue'); // adjacent run at x=2 — no move needed
        $this->gui->present();
        $content = $this->output->fetch();

        // Only the first run's absolute move ("…H") is emitted; the adjacent
        // second run needs neither an absolute nor a relative move.
        self::assertSame(1, substr_count($content, 'H'));
        self::assertStringNotContainsString("\033[", substr($content, strpos($content, 'cd') - 5, 5));
        $this->gui->endDiff();
    }

    public function test_diff_same_row_gap_uses_relative_move(): void
    {
        $this->gui->beginDiff(20, 1);
        $this->gui->render(0, 0, 'a'); // ends at x=1
        $this->gui->render(3, 0, 'b'); // gap of 2 -> "\e[2C", not an absolute jump
        $this->gui->present();
        $content = $this->output->fetch();

        self::assertStringContainsString("\033[2C", $content);
        self::assertSame(1, substr_count($content, 'H')); // only the first run is absolute
        $this->gui->endDiff();
    }

    public function test_diff_row_change_uses_absolute_move(): void
    {
        $this->gui->beginDiff(20, 3);
        $this->gui->render(0, 0, 'a');
        $this->gui->render(0, 1, 'b'); // different row -> absolute move
        $this->gui->present();
        $content = $this->output->fetch();

        self::assertSame(2, substr_count($content, 'H'));
        self::assertStringContainsString("\033[2;0H", $content); // second run absolute at row 1
        $this->gui->endDiff();
    }

    public function test_present_without_diff_session_is_noop(): void
    {
        $this->gui->present();

        self::assertSame('', $this->output->fetch());
    }

    public function test_move_cursor_emits_absolute_position(): void
    {
        $this->gui->moveCursor(3, 2);

        // Symfony Cursor::moveToPosition(col, row) emits "\e[{row+1};{col}H".
        self::assertStringContainsString("\033[3;3H", $this->output->fetch());
    }

    public function test_cursor_home_moves_to_origin(): void
    {
        $this->gui->cursorHome();

        self::assertStringContainsString("\033[1;0H", $this->output->fetch());
    }
}
