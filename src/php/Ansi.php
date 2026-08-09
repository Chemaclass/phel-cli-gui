<?php

declare(strict_types=1);

namespace PhelCliGui;

/**
 * The cursor-movement escape sequences the renderer emits.
 *
 * Symfony's Cursor is still used for the verbs it gets right (hide/show,
 * clear screen/line/output), but not for positioning: its moveToPosition()
 * writes the column through unincremented — "\e[{row}+1;{column}H" — while
 * CUP counts columns from 1. That renders every column >= 1 one cell to the
 * left and collapses columns 0 and 1 onto the same cell. These builders emit
 * the sequence the coordinates actually mean.
 *
 * Building the escapes as plain strings also lets a draw concatenate its move
 * and its text into a single write instead of one write per part.
 */
final class Ansi
{
    /** Absolute move to a 0-indexed (column, row) — CUP, 1-indexed on the wire. */
    public static function moveTo(int $column, int $row): string
    {
        return "\x1b[" . ($row + 1) . ';' . ($column + 1) . 'H';
    }

    /** Relative move `columns` cells to the right — CUF. */
    public static function moveRight(int $columns): string
    {
        return "\x1b[" . $columns . 'C';
    }
}
