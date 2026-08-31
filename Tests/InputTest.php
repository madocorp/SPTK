<?php

namespace SPTK\Tests;

use SPTK\Core\Clipboard;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\Theme;
use SPTK\Widgets\Input;

return [
  'input renders one line text and cursor' => function(): void {
    $input = new Input('name', 'abc');
    $input->setFrame(new Rect(0, 0, 5, 1));
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);

    assertSame('abc  ', $buffer->line(0), 'Input should render text on one row.');
    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Unfocused input cursor should use the field foreground.');
    assertSame('#555555', $buffer->cell(0, 0)?->bg->hex(), 'Unfocused input cursor should use inactive cursor background.');
    assertSame('#00aaaa', $buffer->cell(1, 0)?->bg->hex(), 'Input field background should match unfocused button background.');

    $input->setFocused(true);
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);

    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused input cursor should use cursor foreground.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused input cursor should use cursor background.');
    assertSame('#00cccc', $buffer->cell(1, 0)?->bg->hex(), 'Focused input field background should be a brighter field color.');
  },

  'focused input brightens themed field background' => function(): void {
    $input = new Input('name', 'abc');
    $input->setTheme(new Theme(inverseBg: '#338833'));
    $input->setFrame(new Rect(0, 0, 5, 1));
    $input->setFocused(true);
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);

    assertSame('#3da33d', $buffer->cell(1, 0)?->bg->hex(), 'Focused panel input should brighten the green field background.');
  },

  'inactive input cursor follows field foreground' => function(): void {
    $input = new Input('name', 'abc');
    $input->setTheme(new Theme(inverseFg: '#eeeeee'));
    $input->setFrame(new Rect(0, 0, 5, 1));
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);

    assertSame('#eeeeee', $buffer->cell(0, 0)?->fg->hex(), 'Inactive input cursor should follow a bright field foreground.');
  },

  'inactive input hides placeholder when empty' => function(): void {
    $input = (new Input('name'))->setPlaceholder('full, max, or WxH');
    $input->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $input->render($buffer);

    assertSame('          ', $buffer->line(0), 'Inactive empty input should not render the placeholder text.');
    assertSame('', $input->getValue(), 'Placeholder should not change the input value.');
  },

  'focused input renders placeholder after cursor' => function(): void {
    $input = (new Input('name'))->setPlaceholder('full, max');
    $input->setFocused(true);
    $input->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $input->render($buffer);

    assertSame(' full, max', $buffer->line(0), 'Focused empty input should render the placeholder after the cursor.');
    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused placeholder input should keep the cursor visible.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused placeholder input should keep the cursor background.');
    assertSame('#00ffff', $buffer->cell(1, 0)?->fg->hex(), 'Focused placeholder should use marker foreground.');
  },

  'input edits text on one line' => function(): void {
    $input = new Input('name', 'ab');

    assertSame(true, $input->handle(InputEvent::text("c\nd")), 'Text input should be handled.');
    assertSame('c dab', $input->text(), 'Inserted multiline text should be normalized to one line at the cursor.');
    assertSame(true, $input->handle(InputEvent::key('End')), 'End should be handled.');
    assertSame(true, $input->handle(InputEvent::key('Backspace')), 'Backspace should be handled.');
    assertSame('c da', $input->text(), 'Backspace should delete before the cursor.');
    assertSame(false, $input->handle(InputEvent::key('Up')), 'Vertical movement should not be handled.');
    assertSame(false, $input->handle(InputEvent::key('Down')), 'Vertical movement should not be handled.');
    assertSame(false, $input->handle(InputEvent::key('Enter')), 'Enter should not edit a one-line input.');
  },

  'input supports selection clipboard and undo redo' => function(): void {
    $input = new Input('name', 'abcd');
    $input->handle(InputEvent::key('Right', ['shift' => true]));
    $input->handle(InputEvent::key('Right', ['shift' => true]));
    $input->setTheme(new Theme(inverseFg: '#eeeeee'));
    $input->setFrame(new Rect(0, 0, 6, 1));
    $buffer = new GridBuffer(6, 1);
    $input->render($buffer);
    assertSame('#eeeeee', $buffer->cell(1, 0)?->fg->hex(), 'Inactive input selection should follow the field foreground.');
    assertSame('#555555', $buffer->cell(1, 0)?->bg->hex(), 'Inactive input selection should use inactive cursor background.');

    $input->handle(InputEvent::key('C', ['ctrl' => true]));

    assertSame('abc', Clipboard::get(), 'Copy should match the shared text cursor selection behavior.');

    $input->handle(InputEvent::key('End'));
    $input->handle(InputEvent::key('V', ['ctrl' => true]));
    assertSame('abcdabc', $input->text(), 'Paste should insert clipboard text at the cursor.');

    $input->handle(InputEvent::key('Z', ['ctrl' => true]));
    assertSame('abcd', $input->text(), 'Undo should restore text.');

    $input->handle(InputEvent::key('Y', ['ctrl' => true]));
    assertSame('abcdabc', $input->text(), 'Redo should restore undone edit.');
  },

  'input keeps cursor visible with horizontal scrolling' => function(): void {
    $input = new Input('name', 'abcdef');
    $input->setFrame(new Rect(0, 0, 4, 1));
    $input->setCursorPosition(6);
    $buffer = new GridBuffer(4, 1);
    $input->render($buffer);

    assertSame('def ', $buffer->line(0), 'Input should scroll horizontally to keep the cursor visible.');
    assertSame('#555555', $buffer->cell(3, 0)?->bg->hex(), 'End-of-line cursor should be visible without rendering a paragraph marker.');
    assertSame(['x' => 3], $input->scrollState(), 'Scroll state should expose horizontal offset.');
  },

  'input keeps cursor visible after frame shrinks' => function(): void {
    $input = new Input('name', 'abcdef');
    $input->setFrame(new Rect(0, 0, 8, 1));
    $input->setCursorPosition(6);
    $input->setFrame(new Rect(0, 0, 4, 1));
    $buffer = new GridBuffer(4, 1);
    $input->render($buffer);

    assertSame('def ', $buffer->line(0), 'Input should recalculate scroll when its frame gets narrower.');
    assertSame('#555555', $buffer->cell(3, 0)?->bg->hex(), 'End-of-line cursor should stay visible after the frame shrinks.');
    assertSame(['x' => 3], $input->scrollState(), 'Shrinking the frame should update the horizontal offset around the cursor.');
  },

  'input ctrl home and end page horizontally' => function(): void {
    $input = new Input('name', 'abcdefghijklmnopqrst');
    $input->setFrame(new Rect(0, 0, 6, 1));

    $input->setCursorPosition(18);
    $input->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame(13, $input->cursorPosition(), 'Ctrl+Home should move to the first visible column.');
    $input->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame(7, $input->cursorPosition(), 'Repeated Ctrl+Home should page left horizontally.');
    $input->handle(InputEvent::key('Home', ['ctrl' => true]));
    $input->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame(0, $input->cursorPosition(), 'Repeated Ctrl+Home should reach the input start.');

    $input->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame(5, $input->cursorPosition(), 'Ctrl+End should move to the last visible column.');
    $input->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame(11, $input->cursorPosition(), 'Repeated Ctrl+End should page right horizontally.');
  },

  'input draws horizontal scrollbar on the input row' => function(): void {
    $input = new Input('name', 'abcdef');
    $input->setFrame(new Rect(0, 0, 4, 1));
    $buffer = new GridBuffer(4, 1);
    $input->render($buffer);

    assertSame('abcd', $buffer->line(0), 'Input content should stay on its only row when horizontal scrolling is possible.');
  },

  'input read only blocks mutations but allows navigation and copy' => function(): void {
    $input = new Input('name', 'abc');
    $input->setReadOnly(true);

    assertSame(true, $input->handle(InputEvent::text('x')), 'Read-only text input should be consumed.');
    assertSame('abc', $input->text(), 'Read-only input should not insert text.');
    assertSame(true, $input->handle(InputEvent::key('End')), 'Read-only input should allow navigation.');
    assertSame(true, $input->handle(InputEvent::key('Backspace')), 'Read-only input should consume destructive keys.');
    assertSame('abc', $input->text(), 'Read-only input should not delete text.');

    $input->setFrame(new Rect(0, 0, 5, 1));
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);
    assertSame('#4d8989', $buffer->cell(1, 0)?->bg->hex(), 'Read-only input field background should be desaturated.');

    $input->setFocused(true);
    $buffer = new GridBuffer(5, 1);
    $input->render($buffer);
    assertSame('#5ca4a4', $buffer->cell(1, 0)?->bg->hex(), 'Focused read-only input field background should brighten the desaturated color.');
  },

  'input can mask password text' => function(): void {
    $input = new Input('password', 'secret');
    $input->setPassword();
    $input->setFrame(new Rect(0, 0, 8, 1));
    $buffer = new GridBuffer(8, 1);
    $input->render($buffer);

    assertSame('******  ', $buffer->line(0), 'Password input should render masked characters.');
    assertSame('secret', $input->getValue(), 'Password input should keep the real value.');

    $input->handle(InputEvent::key('End'));
    $input->handle(InputEvent::text('1'));
    assertSame('secret1', $input->getValue(), 'Password input should edit the real value.');
  },
];
