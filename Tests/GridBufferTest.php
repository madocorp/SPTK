<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\GridBuffer;
use SPTK\Core\Rect;

return [
  'grid writes and clips text' => function(): void {
    $buffer = new GridBuffer(5, 2);
    $buffer->clearDirty();
    $buffer->write(3, 0, 'abcd');
    assertSame('   ab', $buffer->line(0), 'Write should clip at the right edge.');
    assertSame(2, count($buffer->dirtyRegions()), 'Only visible cells should be dirty.');
  },

  'grid fills clipped rect' => function(): void {
    $buffer = new GridBuffer(4, 3);
    $buffer->fill(new Rect(2, 1, 5, 3), 'x');
    assertSame('    ', $buffer->line(0), 'First row should be untouched.');
    assertSame('  xx', $buffer->line(1), 'Fill should affect visible rect area.');
    assertSame('  xx', $buffer->line(2), 'Fill should clip to bottom edge.');
  },

  'grid blits source buffer' => function(): void {
    $source = new GridBuffer(3, 1);
    $source->write(0, 0, 'abc');
    $target = new GridBuffer(5, 1);
    $target->blit($source, 1, 0);
    assertSame(' abc ', $target->line(0), 'Blit should copy source cells into target.');
  },

  'cell stores one glyph' => function(): void {
    $cell = new Cell('abc');
    assertSame('a', $cell->glyph, 'Cell should store a single glyph.');
  },
];

