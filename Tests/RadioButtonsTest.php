<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\RadioButtons;

return [
  'radio buttons render inline when choices fit' => function(): void {
    $radios = new RadioButtons('mode', ['a' => 'A', 'b' => 'B']);
    $radios->setFrame(new Rect(0, 0, 12, 1));
    $buffer = new GridBuffer(12, 1);
    $radios->render($buffer);

    assertSame('(o) A  ( ) B', $buffer->line(0), 'Choices that fit should render on one row with two spaces between them.');
    assertSame('a', $radios->getValue(), 'Associative choices should report the selected key.');
    assertSame('#00aaaa', $buffer->cell(7, 0)?->bg->hex(), 'Unselected radio marker should use the same background as an unfocused checkbox marker.');
  },

  'radio buttons use text as value for numeric keys' => function(): void {
    $radios = new RadioButtons('size', ['Small', 'Large']);

    assertSame('Small', $radios->getValue(), 'Numeric choices should report the selected text.');
    $radios->setValue('Large');
    assertSame('Large', $radios->getValue(), 'setValue should select numeric choices by text.');
  },

  'radio buttons render vertically when choices do not fit inline' => function(): void {
    $radios = new RadioButtons('mode', ['alpha' => 'Alpha', 'beta' => 'Beta']);
    $radios->setFrame(new Rect(0, 0, 10, 2));
    $buffer = new GridBuffer(10, 2);
    $radios->render($buffer);

    assertSame('(o) Alpha ', $buffer->line(0), 'First choice should render on the first row.');
    assertSame('( ) Beta  ', $buffer->line(1), 'Second choice should render on the second row when inline layout does not fit.');
    assertSame(2, $radios->preferredRowsForColumns(10), 'Preferred rows should match wrapped vertical layout.');
  },

  'radio buttons can force vertical layout' => function(): void {
    $radios = new RadioButtons('mode', ['a' => 'A', 'b' => 'B'], ['vertical' => true]);
    $radios->setFrame(new Rect(0, 0, 12, 2));
    $buffer = new GridBuffer(12, 2);
    $radios->render($buffer);

    assertSame('(o) A       ', $buffer->line(0), 'Forced vertical layout should put the first choice on row one.');
    assertSame('( ) B       ', $buffer->line(1), 'Forced vertical layout should put the second choice on row two.');
    assertSame(2, $radios->preferredRows(), 'Forced vertical layout should prefer one row per choice.');
  },

  'radio buttons handle exclusive keyboard selection' => function(): void {
    $radios = new RadioButtons('mode', ['a' => 'A', 'b' => 'B', 'c' => 'C']);

    assertSame(true, $radios->handle(InputEvent::key('Right')), 'Right should be handled.');
    assertSame('b', $radios->getValue(), 'Right should select the next choice.');
    assertSame(true, $radios->handle(InputEvent::key('Down')), 'Down should be handled.');
    assertSame('c', $radios->getValue(), 'Down should select the next choice.');
    assertSame(true, $radios->handle(InputEvent::key('Left')), 'Left should be handled.');
    assertSame('b', $radios->getValue(), 'Left should select the previous choice.');
    assertSame(true, $radios->handle(InputEvent::key('Home')), 'Home should be handled.');
    assertSame('a', $radios->getValue(), 'Home should select the first choice.');
    assertSame(true, $radios->handle(InputEvent::key('End')), 'End should be handled.');
    assertSame('c', $radios->getValue(), 'End should select the last choice.');
    assertSame(true, $radios->handle(InputEvent::key('Space')), 'Space should be handled.');
    assertSame('c', $radios->getValue(), 'Space should keep the selected choice.');
    assertSame(false, $radios->handle(InputEvent::key('Escape')), 'Unrelated keys should not be handled.');
  },

  'radio buttons clear previous render without clearing unused columns' => function(): void {
    $radios = new RadioButtons('mode', ['long' => 'LongText', 'no' => 'No']);
    $radios->setFrame(new Rect(0, 0, 14, 2));
    $buffer = new GridBuffer(14, 2);
    $buffer->fill(new Rect(0, 0, 14, 2), '.');
    $radios->render($buffer);

    assertSame('(o) LongText..', $buffer->line(0), 'Initial vertical render should leave unused columns alone.');

    $radios->setItems(['no' => 'No']);
    $radios->render($buffer);

    assertSame('(o) No      ..', $buffer->line(0), 'Radio buttons should clear stale text without clearing unused columns.');
    assertSame('      ........', $buffer->line(1), 'Rows no longer used by the group should be cleared only where the group rendered.');
  },
];
