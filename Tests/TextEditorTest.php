<?php

namespace SPTK\Tests;

use SPTK\Core\Clipboard;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\Tokenizer;
use SPTK\Widgets\TextEditor;

class QuotedTextTokenizer extends Tokenizer {
  protected array $styleMap = [
    'TEXT' => 'plain',
    'STRING' => 'string',
  ];
  protected array $styleColors = [
    'string' => ['fg' => '#00ffff', 'bg' => '#0000aa'],
  ];
  protected array $contextSwitchers = [
    ['type' => 'STRING', 'start' => '"', 'end' => '"', 'tokenizer' => QuotedStringTokenizer::class],
  ];
  protected array $regexpRules = [
    ['type' => 'TEXT', 'regexp' => '/^[^"]+/u'],
  ];
}

class QuotedStringTokenizer extends Tokenizer {
  protected array $styleMap = [
    'TEXT' => 'string',
    'STRING' => 'string',
  ];
  protected array $regexpRules = [
    ['type' => 'TEXT', 'regexp' => '/^[^"]+/u'],
  ];
}

class TextEditorScrollbarProbe extends TextEditor {
  public function needsHorizontalScrollbarProbe(): bool {
    return $this->needsHorizontalScrollbar();
  }
}

class FencedCodeTokenizer extends Tokenizer {
  protected array $styleMap = [
    'TEXT' => 'plain',
    'CODE_BLOCK' => 'code-block',
  ];
  protected array $contextSwitchers = [
    ['type' => 'CODE_BLOCK', 'start' => '```', 'endRegexp' => '/^``` */', 'tokenizer' => FencedCodeBodyTokenizer::class],
  ];
  protected array $regexpRules = [
    ['type' => 'TEXT', 'regexp' => '/^.*/u'],
  ];
}

class FencedCodeBodyTokenizer extends Tokenizer {
  protected array $styleMap = [
    'CODE_BLOCK' => 'code-block',
  ];
  protected array $regexpRules = [
    ['type' => 'CODE_BLOCK', 'regexp' => '/^[^`]+/u'],
    ['type' => 'CODE_BLOCK', 'regexp' => '/^./u'],
  ];
}

return [
  'text editor inserts multiline text and moves cursor' => function(): void {
    $editor = new TextEditor('editor', 'ac');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::text('b'));
    $editor->handle(InputEvent::key('Enter'));
    $editor->handle(InputEvent::text('d'));
    assertSame("ab\ndc", $editor->text(), 'Text input and Enter should edit at the cursor.');
    assertSame([1, 1], $editor->cursorPosition(), 'Cursor should move after inserted text.');
  },

  'text editor handles enter aliases through input actions' => function(): void {
    $editor = new TextEditor('editor', 'ab');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::key('KeypadEnter'));
    assertSame("a\nb", $editor->text(), 'Keypad Enter should insert an editor newline.');
  },

  'text editor backspace and delete join lines' => function(): void {
    $editor = new TextEditor('editor', "ab\ncd");
    $editor->setCursorPosition(1, 0);
    $editor->handle(InputEvent::key('Backspace'));
    assertSame('abcd', $editor->text(), 'Backspace at line start should join with previous line.');
    $editor->setText("ab\ncd");
    $editor->setCursorPosition(0, 2);
    $editor->handle(InputEvent::key('Delete'));
    assertSame('abcd', $editor->text(), 'Delete at line end should join with next line.');
  },

  'text editor shift selection replaces selected text' => function(): void {
    $editor = new TextEditor('editor', 'abcd');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::text('X'));
    assertSame('aXd', $editor->text(), 'Text input should replace the selected range.');
  },

  'text editor copy cut paste use clipboard' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $editor = new TextEditor('editor', 'abcd');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->setFieldColors('#111111', '#dddddd');
    $editor->setFrame(new Rect(0, 0, 6, 1));
    $buffer = new GridBuffer(6, 1);
    $editor->render($buffer);
    assertSame('#111111', $buffer->cell(1, 0)?->fg->hex(), 'Inactive editor selection should follow the field foreground.');
    assertSame('#555555', $buffer->cell(1, 0)?->bg->hex(), 'Inactive editor selection should use inactive cursor background.');

    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame('bc', Clipboard::get(), 'Copy should include the selected text and cursor character.');
    $editor->setCursorPosition(0, 3);
    $editor->handle(InputEvent::key('V', ['ctrl' => true]));
    assertSame('abcbcd', $editor->text(), 'Paste should insert clipboard text.');
    $editor->handle(InputEvent::key('A', ['ctrl' => true]));
    $editor->handle(InputEvent::key('X', ['ctrl' => true]));
    assertSame('', $editor->text(), 'Cut should remove the selected text.');
    assertSame('abcbcd', Clipboard::get(), 'Cut should copy the selected text.');
  },

  'text editor right selection copy includes cursor character' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $editor = new TextEditor('editor', 'abcd');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame('bcd', Clipboard::get(), 'Copy should include two selected characters plus the cursor character.');
  },

  'text editor copy includes newline at line ending cursor' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $editor = new TextEditor('editor', "ab\ncd");
    $editor->setCursorPosition(0, 2);
    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame("\n", Clipboard::get(), 'Copy at end of a non-final line should copy the newline character.');

    $editor->setCursorPosition(0, 0);
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::key('Right', ['shift' => true]));
    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame("ab\n", Clipboard::get(), 'Copy should include the newline when the cursor is on the line ending.');
  },

  'text editor collapsed cursor copies character without replacing insert target' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $editor = new TextEditor('editor', 'ab');
    $editor->setCursorPosition(0, 1);
    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame('b', Clipboard::get(), 'Copy at a collapsed cursor should copy the character under it.');
    $editor->setCursorPosition(0, 2);
    $editor->handle(InputEvent::text('X'));
    assertSame('abX', $editor->text(), 'Typing at end of line should insert without deleting through the cursor.');
    assertSame([0, 3], $editor->cursorPosition(), 'Cursor should move after end-of-line insertion.');
  },

  'text editor read only blocks mutations but allows copy' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $editor = new TextEditor('editor', 'abc');
    $editor->setReadOnly(true);
    $editor->handle(InputEvent::text('x'));
    $editor->handle(InputEvent::key('Backspace'));
    assertSame('abc', $editor->text(), 'Read-only editor should not mutate on text or delete keys.');
    $editor->handle(InputEvent::key('A', ['ctrl' => true]));
    $editor->handle(InputEvent::key('C', ['ctrl' => true]));
    assertSame('abc', Clipboard::get(), 'Read-only editor should still allow copy.');

    $editor->setFieldColors('#000000', '#338833');
    $editor->setFrame(new Rect(0, 0, 4, 2));
    $buffer = new GridBuffer(4, 2);
    $editor->render($buffer);
    assertSame('#547154', $buffer->cell(1, 0)?->bg->hex(), 'Read-only editor text should use a desaturated field background.');

    $editor->setFocused(true);
    $buffer = new GridBuffer(4, 2);
    $editor->render($buffer);
    assertSame('#658865', $buffer->cell(1, 0)?->bg->hex(), 'Focused read-only editor text should brighten the desaturated field background.');
  },

  'text editor undo redo restores text' => function(): void {
    $editor = new TextEditor('editor', '');
    $editor->insertText('abc');
    assertSame('abc', $editor->text(), 'Insert should change text.');
    $editor->handle(InputEvent::key('Z', ['ctrl' => true]));
    assertSame('', $editor->text(), 'Undo should restore previous text.');
    $editor->handle(InputEvent::key('Y', ['ctrl' => true]));
    assertSame('abc', $editor->text(), 'Redo should restore undone text.');
  },

  'text editor save and restore state keeps cursor and scroll' => function(): void {
    $editor = new TextEditor('editor', "zero\none\ntwo\nthree");
    $editor->setFrame(new Rect(0, 0, 8, 2));
    $editor->setCursorPosition(2, 1);
    $state = $editor->saveState();
    $editor->setCursorPosition(0, 0);
    $editor->restoreState($state);
    assertSame([2, 1], $editor->cursorPosition(), 'Restored state should restore cursor.');
    assertSame(['x' => 0, 'y' => 1], $editor->scrollState(), 'Restored state should restore cursor-following scroll.');
  },

  'text editor scroll follows cursor movement past viewport' => function(): void {
    $editor = new TextEditor('editor', implode("\n", range(0, 30)));
    $editor->setFrame(new Rect(0, 0, 8, 4));
    for ($i = 0; $i < 10; $i++) {
      $editor->handle(InputEvent::key('Down'));
    }
    assertSame([10, 0], $editor->cursorPosition(), 'Down should move the cursor past the initial viewport.');
    assertSame(['x' => 0, 'y' => 7], $editor->scrollState(), 'Vertical scroll should follow cursor movement.');
  },

  'text editor scroll follows cursor after frame assignment' => function(): void {
    $editor = new TextEditor('editor', implode("\n", range(0, 30)));
    $editor->setCursorPosition(10, 0);
    $editor->setFrame(new Rect(0, 0, 8, 4));
    assertSame(['x' => 0, 'y' => 0], $editor->scrollState(), 'Frame assignment should not follow the cursor during transient relayout.');
  },

  'text editor transient smaller frame does not start scroll early' => function(): void {
    $editor = new TextEditor('editor', implode("\n", range(0, 30)));
    $editor->setFrame(new Rect(0, 0, 95, 12));
    for ($i = 0; $i < 10; $i++) {
      $editor->handle(InputEvent::key('Down'));
      $editor->setFrame(new Rect(0, 0, 94, 10));
      $editor->setFrame(new Rect(0, 0, 96, 12));
      $editor->setFrame(new Rect(0, 0, 95, 12));
    }
    assertSame([10, 0], $editor->cursorPosition(), 'Down should move the cursor to row ten.');
    assertSame(['x' => 0, 'y' => 0], $editor->scrollState(), 'Temporary smaller layout passes should not keep an early scroll offset.');
  },

  'text editor page up and down use visible editor rows' => function(): void {
    $editor = new TextEditor('editor', implode("\n", range(0, 30)));
    $editor->setFrame(new Rect(0, 0, 8, 6));

    $editor->handle(InputEvent::key('PageDown'));
    assertSame([6, 0], $editor->cursorPosition(), 'PageDown should move by the visible editor height.');
    assertSame(['x' => 0, 'y' => 1], $editor->scrollState(), 'PageDown should keep the cursor visible.');

    $editor->handle(InputEvent::key('PageUp'));
    assertSame([0, 0], $editor->cursorPosition(), 'PageUp should move by the visible editor height.');
    assertSame(['x' => 0, 'y' => 0], $editor->scrollState(), 'PageUp should keep the cursor visible.');
  },

  'text editor scrollbars overlay content without stealing rows or columns' => function(): void {
    $lines = array_merge(['0123456789abcdef'], range(1, 30));
    $editor = new TextEditor('editor', implode("\n", $lines));
    $editor->setFrame(new Rect(0, 0, 8, 4));

    $editor->setCursorPosition(3, 0);
    assertSame(['x' => 0, 'y' => 0], $editor->scrollState(), 'Horizontal scrollbar should not reduce the visible editor rows.');
    $editor->handle(InputEvent::key('Down'));
    assertSame(['x' => 0, 'y' => 1], $editor->scrollState(), 'Vertical scrolling should start only after the full frame height is exceeded.');

    $editor->setCursorPosition(0, 7);
    assertSame(['x' => 0, 'y' => 0], $editor->scrollState(), 'Vertical scrollbar should not reduce the visible editor columns.');
    $editor->handle(InputEvent::key('Right'));
    assertSame(['x' => 1, 'y' => 0], $editor->scrollState(), 'Horizontal scrolling should start only after the full frame width is exceeded.');

    $buffer = new GridBuffer(8, 4);
    $editor->setCursorPosition(3, 0);
    $editor->render($buffer);
    assertSame('3', $buffer->cell(0, 3)?->glyph, 'The final frame row should still render editor content.');
    assertSame('7', $buffer->cell(7, 0)?->glyph, 'The final frame column should still render editor content.');
  },

  'text editor keeps end of longest line cursor visible' => function(): void {
    $editor = new TextEditor('editor', 'abcdefgh');
    $editor->setFrame(new Rect(0, 0, 8, 1));
    $editor->setCursorPosition(0, 8);

    assertSame(['x' => 1, 'y' => 0], $editor->scrollState(), 'End-of-line cursor should be part of the horizontal scroll extent.');

    $buffer = new GridBuffer(8, 1);
    $editor->render($buffer);
    assertSame('¶', $buffer->cell(7, 0)?->glyph, 'The paragraph cursor should render at the end of the longest line.');
  },

  'text editor ctrl page up and down preserve horizontal position' => function(): void {
    $editor = new TextEditor('editor', "0123456789\nabcdefghij\nklmnopqrst");
    $editor->setFrame(new Rect(0, 0, 8, 3));
    $editor->setCursorPosition(1, 4);

    $editor->handle(InputEvent::key('PageDown', ['ctrl' => true]));
    assertSame([2, 4], $editor->cursorPosition(), 'Ctrl+PageDown should move to the last row without changing columns.');

    $editor->handle(InputEvent::key('PageUp', ['ctrl' => true]));
    assertSame([0, 4], $editor->cursorPosition(), 'Ctrl+PageUp should move to the first row without changing columns.');
  },

  'text editor ctrl home and end page horizontally' => function(): void {
    $editor = new TextEditor('editor', 'abcdefghijklmnopqrst');
    $editor->setFrame(new Rect(0, 0, 6, 2));

    $editor->setCursorPosition(0, 18);
    $editor->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([0, 13], $editor->cursorPosition(), 'Ctrl+Home should move to the first visible column.');
    $editor->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([0, 7], $editor->cursorPosition(), 'Repeated Ctrl+Home should page left horizontally.');
    $editor->handle(InputEvent::key('Home', ['ctrl' => true]));
    $editor->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([0, 0], $editor->cursorPosition(), 'Repeated Ctrl+Home should reach the line start.');

    $editor->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame([0, 5], $editor->cursorPosition(), 'Ctrl+End should move to the last visible column.');
    $editor->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame([0, 11], $editor->cursorPosition(), 'Repeated Ctrl+End should page right horizontally.');
  },

  'text editor renders highlight ranges and syntax token colors' => function(): void {
    $editor = new TextEditor('editor', 'select 42');
    $editor->setHighlighter([
      ['regex' => '/^select\b/u', 'style' => 'keyword'],
      ['regex' => '/^\s+/u', 'style' => 'plain'],
      ['regex' => '/^\d+/u', 'style' => 'number'],
    ]);
    $editor->setHighlightRanges([[0, 7, 0, 9]]);
    $editor->setCursorPosition(0, 9);
    $editor->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $editor->render($buffer);
    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Keyword token should use keyword color.');
    assertSame('#000000', $buffer->cell(7, 0)?->fg->hex(), 'Highlight range should override token foreground.');
    assertSame('#ffff00', $buffer->cell(7, 0)?->bg->hex(), 'Highlight range should override token background.');
  },

  'tokenizer preserves multiline context' => function(): void {
    $tokens = Tokenizer::start(['plain "open', 'still string" plain'], QuotedTextTokenizer::class);
    assertSame('STRING', $tokens[0]['tokens'][1]['type'], 'Opening quote should switch to string context.');
    assertSame(2, count($tokens[0]['context']), 'First line should save the active string context.');
    assertSame('string', $tokens[1]['tokens'][0]['style'], 'Next line should resume string styling.');
    assertSame(1, count($tokens[1]['context']), 'Closing quote should restore the base tokenizer context.');
  },

  'text editor accepts tokenizer classes' => function(): void {
    $editor = new TextEditor('editor', '"quoted" plain');
    $editor->setTokenizer(QuotedTextTokenizer::class);
    $editor->setFrame(new Rect(0, 0, 14, 1));
    $buffer = new GridBuffer(14, 1);
    $editor->render($buffer);
    assertSame('#00ffff', $buffer->cell(1, 0)?->fg->hex(), 'String body should use tokenizer-provided string style.');
    assertSame('#aaaaaa', $buffer->cell(9, 0)?->fg->hex(), 'Plain text after the string should use plain style.');
  },

  'text editor auto wrap prefers word boundaries' => function(): void {
    $editor = new TextEditor('editor', 'alpha beta gamma delta');
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 3));
    $buffer = new GridBuffer(10, 3);
    $editor->render($buffer);
    assertSame('alpha beta', rtrim($buffer->line(0)), 'First wrapped row should keep complete words.');
    assertSame('gamma', rtrim($buffer->line(1)), 'Second wrapped row should start after the word boundary.');
    assertSame('delta', rtrim($buffer->line(2)), 'Third wrapped row should contain the final word.');
  },

  'text editor auto wrap keeps prose wrapped with exempt styles configured' => function(): void {
    $editor = new TextEditor('editor', 'alpha beta gamma delta');
    $editor->setHighlighter([
      ['regex' => '/^[^`]+/u', 'style' => 'plain'],
      ['regex' => '/^./u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 3));

    $buffer = new GridBuffer(10, 3);
    $editor->render($buffer);
    assertSame('alpha beta', rtrim($buffer->line(0)), 'Plain prose should still wrap when exempt styles are configured.');
    assertSame('gamma', rtrim($buffer->line(1)), 'Wrapped prose should continue on the next visual row.');
  },

  'text editor auto wrap does not wrap fully exempt code block lines' => function(): void {
    $editor = new TextEditor('editor', "CODE 0123456789abcdef\nplain text");
    $editor->setHighlighter([
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 8, 4));

    $buffer = new GridBuffer(8, 4);
    $editor->render($buffer);
    assertSame('CODE 012', $buffer->line(0), 'A fully exempt row should paint as one horizontally clipped row.');
    assertSame('plain', rtrim($buffer->line(1)), 'The next document line should render on the next visual row, not after wrapped code.');
  },

  'text editor auto wrap keeps inline code prose wrapped' => function(): void {
    $editor = new TextEditor('editor', '`code` alpha beta gamma');
    $editor->setHighlighter([
      ['regex' => '/^`[^`]+`/u', 'style' => 'code-block'],
      ['regex' => '/^[^`]+/u', 'style' => 'plain'],
      ['regex' => '/^./u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 3));

    $buffer = new GridBuffer(10, 3);
    $editor->render($buffer);
    assertSame('`code`', rtrim($buffer->line(0)), 'Inline code plus prose should not exempt the whole row from wrapping.');
    assertSame('alpha beta', rtrim($buffer->line(1)), 'Plain prose after inline code should wrap normally.');
  },

  'text editor auto wrap shows horizontal scrollbar only for long exempt rows' => function(): void {
    $editor = new TextEditorScrollbarProbe('editor', 'alpha beta gamma delta');
    $editor->setHighlighter([
      ['regex' => '/^[^`]+/u', 'style' => 'plain'],
      ['regex' => '/^./u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 8, 3));
    assertSame(false, $editor->needsHorizontalScrollbarProbe(), 'Wrapped prose alone should not require a horizontal scrollbar.');

    $editor = new TextEditorScrollbarProbe('editor', 'CODE 0123456789abcdef');
    $editor->setHighlighter([
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 8, 3));
    assertSame(true, $editor->needsHorizontalScrollbarProbe(), 'A long exempt row should require a horizontal scrollbar in auto-wrap mode.');
  },

  'text editor auto wrap applies horizontal scroll only to exempt rows' => function(): void {
    $editor = new TextEditor('editor', "CODE 0123456789\nalpha beta gamma");
    $editor->setHighlighter([
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 8, 4));
    $editor->setCursorPosition(0, 14);

    assertSame(['x' => 7, 'y' => 0], $editor->scrollState(), 'The exempt row should still support horizontal cursor-following scroll.');
    $buffer = new GridBuffer(8, 4);
    $editor->render($buffer);
    assertSame('23456789', $buffer->line(0), 'Horizontal scroll should shift the exempt row.');
    assertSame('alpha', rtrim($buffer->line(1)), 'Wrapped prose should ignore horizontal scroll and paint from the line start.');
  },

  'text editor auto wrap hangs continuation rows under list markers' => function(): void {
    $editor = new TextEditor('editor', '- alpha beta gamma');
    $editor->setHighlighter([
      ['regex' => '/^[-*+•] /u', 'style' => 'list'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapIndentStyles(['list']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 3));

    $buffer = new GridBuffer(10, 3);
    $editor->render($buffer);
    assertSame('- alpha', rtrim($buffer->line(0)), 'The first list row should include the marker.');
    assertSame('  beta', rtrim($buffer->line(1)), 'The first continuation row should be visually indented after the marker.');
    assertSame('  gamma', rtrim($buffer->line(2)), 'Further continuation rows should keep the same hanging indent.');
    assertSame('- alpha beta gamma', $editor->text(), 'Visual hanging indentation should not modify editor text.');
  },

  'text editor cursor accounts for hanging list indentation' => function(): void {
    $editor = new TextEditor('editor', '- alpha beta gamma');
    $editor->setHighlighter([
      ['regex' => '/^[-*+•] /u', 'style' => 'list'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapIndentStyles(['list']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 3));
    $editor->setCursorPosition(0, 8);

    $buffer = new GridBuffer(10, 3);
    $editor->render($buffer);
    assertSame('b', $buffer->cell(2, 1)?->glyph, 'Cursor on a continuation row should render after the visual list indent.');
  },

  'text editor keeps inline token styles inside hanging list items' => function(): void {
    $editor = new TextEditor('editor', '- **bold** and `code` text');
    $editor->setHighlighter([
      ['regex' => '/^[-*+•] /u', 'style' => 'list'],
      ['regex' => '/^\*\*[^*]+\*\*/u', 'style' => 'bold'],
      ['regex' => '/^`[^`]+`/u', 'style' => 'code'],
      ['regex' => '/^[^*`]+/u', 'style' => 'plain'],
      ['regex' => '/^./u', 'style' => 'plain'],
    ]);
    $editor->setStyleColors([
      'list' => ['fg' => '#005c47'],
      'bold' => ['fg' => '#3d2a00'],
      'code' => ['fg' => '#004f6f'],
    ]);
    $editor->setWrapIndentStyles(['list']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 13, 3));
    $editor->setCursorPosition(0, 25);

    $buffer = new GridBuffer(13, 3);
    $editor->render($buffer);
    assertSame('#005c47', $buffer->cell(0, 0)?->fg->hex(), 'The list marker should use list styling.');
    assertSame('#3d2a00', $buffer->cell(2, 0)?->fg->hex(), 'Bold text inside a list item should keep bold styling.');
    assertSame('#004f6f', $buffer->cell(6, 1)?->fg->hex(), 'Inline code on a wrapped list continuation should keep code styling.');
  },

  'text editor combines hanging list indentation with code block wrap exemption' => function(): void {
    $editor = new TextEditor('editor', "- alpha beta gamma\nCODE 0123456789abcdef");
    $editor->setHighlighter([
      ['regex' => '/^[-*+•] /u', 'style' => 'list'],
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setWrapIndentStyles(['list']);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setAutoWrap();
    $editor->setFrame(new Rect(0, 0, 10, 4));

    $buffer = new GridBuffer(10, 4);
    $editor->render($buffer);
    assertSame('  beta', rtrim($buffer->line(1)), 'List continuation rows should remain indented.');
    assertSame('CODE 01234', $buffer->line(3), 'Code rows should remain unwrapped when list indentation is enabled.');
  },

  'text editor can fill trailing cells from configured line styles' => function(): void {
    $editor = new TextEditor('editor', "CODE x\nplain");
    $editor->setHighlighter([
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setStyleColors([
      'code-block' => ['fg' => '#003f66', 'bg' => '#b7d7e8'],
    ]);
    $editor->setLineFillStyles(['code-block']);
    $editor->setFrame(new Rect(0, 0, 10, 2));

    $buffer = new GridBuffer(10, 2);
    $editor->render($buffer);
    assertSame('#b7d7e8', $buffer->cell(9, 0)?->bg->hex(), 'Configured line styles should fill trailing cells on their row.');
    assertSame('#0000aa', $buffer->cell(9, 1)?->bg->hex(), 'Plain rows should keep normal trailing cell colors.');
    assertSame("CODE x\nplain", $editor->text(), 'Line fill styling should not add spaces to editor text.');
  },

  'text editor can fill empty context lines from tokenizer line styles' => function(): void {
    $editor = new TextEditor('editor', "```\n\nx\n```");
    $editor->setTokenizer(FencedCodeTokenizer::class);
    $editor->setStyleColors([
      'code-block' => ['fg' => '#003f66', 'bg' => '#b7d7e8'],
    ]);
    $editor->setLineFillStyles(['code-block']);
    $editor->setFrame(new Rect(0, 0, 8, 4));

    $buffer = new GridBuffer(8, 4);
    $editor->render($buffer);
    assertSame('#b7d7e8', $buffer->cell(7, 1)?->bg->hex(), 'Empty lines inside a tokenizer context should fill with the context style.');
    assertSame("```\n\nx\n```", $editor->text(), 'Empty code block fill should not add spaces to editor text.');
  },
];
