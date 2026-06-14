<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\FrameSession;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class FrameSessionTest extends TestCase
{
    private function base(): BufferedOutput
    {
        return new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    }

    public function test_starts_inactive(): void
    {
        $frame = new FrameSession();

        self::assertFalse($frame->isActive());
        self::assertNull($frame->output());
        self::assertNull($frame->cursor());
    }

    public function test_begin_activates_and_exposes_buffer_and_cursor(): void
    {
        $frame = new FrameSession();
        $frame->begin($this->base());

        self::assertTrue($frame->isActive());
        self::assertNotNull($frame->output());
        self::assertNotNull($frame->cursor());
    }

    public function test_end_returns_accumulated_payload(): void
    {
        $frame = new FrameSession();
        $frame->begin($this->base());
        $output = $frame->output();
        self::assertNotNull($output);
        $output->write('hello', false, OutputInterface::OUTPUT_RAW);

        self::assertSame('hello', $frame->end(0, 0));
        self::assertFalse($frame->isActive());
    }

    public function test_empty_frame_returns_null(): void
    {
        $frame = new FrameSession();
        $frame->begin($this->base());

        self::assertNull($frame->end(0, 0));
    }

    public function test_nested_end_flushes_only_on_outermost_close(): void
    {
        $frame = new FrameSession();
        $frame->begin($this->base());
        $frame->begin($this->base()); // inner — ignored, depth bump only
        $output = $frame->output();
        self::assertNotNull($output);
        $output->write('x', false, OutputInterface::OUTPUT_RAW);

        self::assertNull($frame->end(0, 0)); // inner close: no flush
        self::assertTrue($frame->isActive());

        self::assertSame('x', $frame->end(0, 0)); // outer close: flush
        self::assertFalse($frame->isActive());
    }

    public function test_request_finalize_parks_cursor_once_at_outermost_end(): void
    {
        $frame = new FrameSession();
        $frame->begin($this->base());
        $frame->requestFinalize();

        // moveToPosition(3, 2) => "\e[3;3H".
        self::assertSame("\033[3;3H", $frame->end(3, 2));
    }

    public function test_end_without_begin_is_noop(): void
    {
        $frame = new FrameSession();

        self::assertNull($frame->end(0, 0));
    }
}
