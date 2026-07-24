<?php

namespace Examples\Tests;

use SPTK\Elements\TextEditor\Cursor;

return [
  'text cursor remembers preferred column across short lines' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 10, 0, 10]);

    $cursor->moveDown();
    assertSame([1, 3, 1, 3], $cursor->get(), 'vertical movement clamps to shorter line end');

    $cursor->moveDown();
    assertSame([2, 10, 2, 10], $cursor->get(), 'vertical movement restores preferred column on longer line');
  },

  'text cursor resets preferred column after horizontal movement' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 10, 0, 10]);

    $cursor->moveDown();
    $cursor->moveBackward();
    $cursor->moveDown();

    assertSame([2, 2, 2, 2], $cursor->get(), 'horizontal movement makes the actual column the new preferred column');
  },

  'text cursor page movement uses preferred column' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'de', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 12, 0, 12]);

    $cursor->movePageDown(2);
    assertSame([2, 2, 2, 2], $cursor->get(), 'page movement clamps to shorter target line');

    $cursor->moveDown();
    assertSame([3, 12, 3, 12], $cursor->get(), 'later vertical movement restores page-move preferred column');
  },

  'text cursor jumps reset preferred column' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 10, 0, 10]);

    $cursor->moveDown();
    $cursor->moveLineStart();
    $cursor->moveDown();
    assertSame([2, 0, 2, 0], $cursor->get(), 'line start resets preferred column');

    $cursor->moveLineEnd();
    $cursor->moveUp();
    assertSame([1, 3, 1, 3], $cursor->get(), 'line end resets preferred column to current line end');
  },

  'text cursor state preserves preferred column' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 9, 0, 9]);
    $cursor->moveDown();

    $restored = new Cursor($lines);
    $restored->restoreState($cursor->saveState());
    $restored->moveDown();

    assertSame([2, 9, 2, 9], $restored->get(), 'saved cursor state keeps preferred column');
  },

  'text cursor selection movement uses preferred column' => function (): void {
    $lines = ['abcdefghijklmnop', 'abc', 'abcdefghijklmnopqrstuvwxyz'];
    $cursor = new Cursor($lines);
    $cursor->set([0, 8, 0, 8]);

    $cursor->moveDown(true);
    assertSame([1, 3, 0, 8], $cursor->get(), 'selection movement clamps caret and keeps anchor');

    $cursor->moveDown(true);
    assertSame([2, 8, 0, 8], $cursor->get(), 'selection movement restores preferred column and keeps anchor');
  },
];
