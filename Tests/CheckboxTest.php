<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\Checkbox;

return [
  'checkbox renders checked state and label' => function(): void {
    $checkbox = new Checkbox('agree', 'Agree');
    $checkbox->setFrame(new Rect(0, 0, 9, 1));
    $buffer = new GridBuffer(9, 1);
    $checkbox->render($buffer);

    assertSame('[ ] Agree', $buffer->line(0), 'Unchecked checkbox should render an empty marker and label.');

    $checkbox->setValue(true);
    $buffer = new GridBuffer(9, 1);
    $checkbox->render($buffer);

    assertSame('[X] Agree', $buffer->line(0), 'Checked checkbox should render an X marker and label.');
  },

  'checkbox handles keyboard toggles' => function(): void {
    $checkbox = new Checkbox('toggle', 'Toggle');

    assertSame(false, $checkbox->getValue(), 'Checkbox should start unchecked.');
    assertSame(true, $checkbox->handle(InputEvent::key('Space')), 'Space should be handled.');
    assertSame(true, $checkbox->getValue(), 'Space should check the checkbox.');
    assertSame(true, $checkbox->handle(InputEvent::key('Enter')), 'Enter should be handled.');
    assertSame(false, $checkbox->getValue(), 'Enter should uncheck the checkbox.');
    assertSame(false, $checkbox->handle(InputEvent::key('Down')), 'Unrelated keys should not be handled.');
  },

  'checkbox clears old label text when rerendered' => function(): void {
    $checkbox = new Checkbox('shorten', 'LongText');
    $checkbox->setFrame(new Rect(0, 0, 14, 1));
    $buffer = new GridBuffer(14, 1);
    $buffer->fill(new Rect(0, 0, 14, 1), '.');
    $checkbox->render($buffer);

    assertSame('[ ] LongText..', $buffer->line(0), 'Initial checkbox label should render without clearing unused columns.');

    $checkbox->setText('No');
    $checkbox->render($buffer);

    assertSame('[ ] No      ..', $buffer->line(0), 'Checkbox should clear stale characters without clearing unused columns.');
  },

  'checkbox marker uses focus colors' => function(): void {
    $checkbox = new Checkbox('focus', 'Focus');
    $checkbox->setFrame(new Rect(0, 0, 9, 1));
    $buffer = new GridBuffer(9, 1);
    $checkbox->render($buffer);

    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Unfocused checkbox marker should use inverse foreground.');
    assertSame('#00aaaa', $buffer->cell(0, 0)?->bg->hex(), 'Unfocused checkbox marker should use inverse background.');

    $checkbox->setFocused(true);
    $buffer = new GridBuffer(9, 1);
    $checkbox->render($buffer);

    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused checkbox marker should use cursor foreground.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused checkbox marker should use cursor background.');
  },
];
