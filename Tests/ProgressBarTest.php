<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\Rect;
use SPTK\Widgets\ProgressBar;

return [
  'progress bar renders description and centered percent' => function(): void {
    $progress = new ProgressBar('progress', 'Loading rows', 25, 100);
    $progress->setFrame(new Rect(0, 0, 20, 2));
    $buffer = new GridBuffer(20, 2);
    $progress->render($buffer);

    assertSame('Loading rows        ', $buffer->line(0), 'Progress description should render on the first row.');
    assertSame('        25%         ', $buffer->line(1), 'Percent text should be centered on the bar row.');
    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Description text should render black.');
    assertSame('#0000aa', $buffer->cell(0, 0)?->bg->hex(), 'Description row should use inherited panel background.');
    assertSame('#00aaaa', $buffer->cell(0, 1)?->bg->hex(), 'Filled bar should use button background color.');
    assertSame('#555555', $buffer->cell(6, 1)?->bg->hex(), 'Unfilled bar should use dark grey background.');
    assertSame('#ffffff', $buffer->cell(8, 1)?->fg->hex(), 'Bar text should render white.');
  },

  'progress bar can show value text' => function(): void {
    $progress = new ProgressBar('progress', 'Copying', 3, 10, ['textMode' => 'value']);
    $progress->setFrame(new Rect(0, 0, 12, 2));
    $buffer = new GridBuffer(12, 2);
    $progress->render($buffer);

    assertSame('   3 / 10   ', $buffer->line(1), 'Value text should be centered on the bar row.');
  },

  'progress bar can hide bar text' => function(): void {
    $progress = new ProgressBar('progress', 'Copying', 5, 10, ['textMode' => 'none']);
    $progress->setFrame(new Rect(0, 0, 10, 2));
    $buffer = new GridBuffer(10, 2);
    $progress->render($buffer);

    assertSame('          ', $buffer->line(1), 'Hidden bar text should leave only the colored bar cells.');
    assertSame('#00aaaa', $buffer->cell(4, 1)?->bg->hex(), 'Filled cells should still render when bar text is hidden.');
    assertSame('#555555', $buffer->cell(5, 1)?->bg->hex(), 'Unfilled cells should still render when bar text is hidden.');
  },

  'progress bar clamps progress to range' => function(): void {
    $progress = new ProgressBar('progress', 'Done');
    $progress->setProgress(12, 10);

    assertSame(10, $progress->value(), 'Progress value should clamp to max.');
    assertSame(10, $progress->max(), 'Progress max should be stored.');
  },
];
