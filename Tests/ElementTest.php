<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Element;
use SPTK\Core\Theme;
use SPTK\Widgets\Dock;
use SPTK\Renderer\TextRenderer;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\DialogBox;
use SPTK\Widgets\ListView;
use SPTK\Widgets\StatusBar;
use SPTK\Widgets\TableView;
use SPTK\Widgets\TextEditor;

return [
  'elements render into root buffer' => function(): void {
    $root = new Dock('root');
    $root->dock(new MenuBar('menu', ['File', 'Query']), 'top');
    $root->dock(new StatusBar('status', 'ready'), 'bottom');
    $root->fill(new DialogBox('body', true, 'Main'));
    $root->setFrame(new Rect(0, 0, 24, 5));
    $buffer = new GridBuffer(24, 5);
    $root->render($buffer);
    assertSame('  1 File   2 Query      ', $buffer->line(0), 'Menu should render numbered items on top row.');
    assertSame('+ Main ----------------+', $buffer->line(1), 'DialogBox border should render in body.');
    assertSame('ready                   ', $buffer->line(4), 'Status should render on bottom row.');
  },

  'table view renders header and rows' => function(): void {
    $table = new TableView('table', ['id', 'name'], [[1, 'alpha'], [2, 'beta']], [4, 8]);
    $table->setFrame(new Rect(0, 0, 12, 3));
    $buffer = new GridBuffer(12, 3);
    $table->render($buffer);
    assertSame('id  name    ', $buffer->line(0), 'Header should render with blank separator cells.');
    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Header should render black text.');
    assertSame('#aaaaaa', $buffer->cell(0, 0)?->bg->hex(), 'Header should render normal foreground gray as background.');
    assertSame('#aaaaaa', $buffer->cell(11, 0)?->bg->hex(), 'Header background should fill the row.');
    assertSame('1   alpha   ', $buffer->line(1), 'First row should render with blank separator cells.');
    assertSame('2   beta    ', $buffer->line(2), 'Second row should render with blank separator cells.');
  },

  'table view moves cell cursor with arrows' => function(): void {
    $table = new TableView('table', ['id', 'name'], [[1, 'alpha'], [2, 'beta']], [4, 8]);
    $table->handle(InputEvent::key('Right'));
    assertSame(0, $table->cursorRow(), 'Right should keep the cursor on the same row.');
    assertSame(1, $table->cursorColumn(), 'Right should move to the next cell.');
    $table->handle(InputEvent::key('Down'));
    assertSame(1, $table->cursorRow(), 'Down should move to the next row.');
    assertSame(1, $table->cursorColumn(), 'Down should keep the cursor in the same column.');
    $table->handle(InputEvent::key('Down'));
    $table->handle(InputEvent::key('Right'));
    assertSame(1, $table->cursorRow(), 'Cursor row should clamp at the last row.');
    assertSame(1, $table->cursorColumn(), 'Cursor column should clamp at the last column.');
    $table->handle(InputEvent::key('Left'));
    $table->handle(InputEvent::key('Up'));
    assertSame(0, $table->cursorRow(), 'Up should move to the previous row.');
    assertSame(0, $table->cursorColumn(), 'Left should move to the previous column.');
  },

  'table view renders cell cursor colors' => function(): void {
    $table = new TableView('table', ['id', 'name'], [[1, 'alpha'], [2, 'beta']], [4, 8]);
    $table->setFrame(new Rect(0, 0, 12, 3));
    $buffer = new GridBuffer(12, 3);
    $table->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(0, 1)?->fg->hex(), 'Unfocused table cursor should use the table foreground.');
    assertSame('#555555', $buffer->cell(0, 1)?->bg->hex(), 'Unfocused table cursor should use dark gray background.');
    assertSame('#aaaaaa', $buffer->cell(3, 1)?->fg->hex(), 'Cell cursor should not overwrite the separator.');
    assertSame('#0000aa', $buffer->cell(3, 1)?->bg->hex(), 'Cell cursor should not overwrite the separator background.');

    $table->setFocused(true);
    $table->handle(InputEvent::key('Right'));
    $buffer = new GridBuffer(12, 3);
    $table->render($buffer);
    assertSame('#ffffff', $buffer->cell(4, 1)?->fg->hex(), 'Focused table cursor should use white foreground.');
    assertSame('#000000', $buffer->cell(4, 1)?->bg->hex(), 'Focused table cursor should use black background.');
  },

  'status bar uses black on gray' => function(): void {
    $status = new StatusBar('status', 'ready');
    $status->setFrame(new Rect(0, 0, 8, 1));
    $buffer = new GridBuffer(8, 1);
    $status->render($buffer);
    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Status text should use black foreground.');
    assertSame('#777777', $buffer->cell(0, 0)?->bg->hex(), 'Status background should use muted gray.');
  },

  'dialog box options can enable border without title' => function(): void {
    $dialog = new DialogBox('dialog', ['border' => true]);
    $dialog->setFrame(new Rect(0, 0, 10, 3));
    $buffer = new GridBuffer(10, 3);
    $dialog->render($buffer);
    assertSame('+--------+', $buffer->line(0), 'Border should render without a title band.');
  },

  'text editor uses centered grid alignment' => function(): void {
    $editor = new TextEditor('editor', '');
    assertSame('center', $editor->gridAlignment(), 'Editor grids should use centered alignment.');
  },

  'cursor colors reflect focus state' => function(): void {
    $list = new ListView('list', ['one']);
    $list->setFrame(new Rect(0, 0, 8, 1));
    $buffer = new GridBuffer(8, 1);
    $list->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(0, 0)?->fg->hex(), 'Unfocused cursor should use the list foreground.');
    assertSame('#555555', $buffer->cell(0, 0)?->bg->hex(), 'Unfocused cursor should use dark gray background.');

    $list->setFocused(true);
    $buffer = new GridBuffer(8, 1);
    $list->render($buffer);
    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused cursor should use white foreground.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused cursor should use black background.');

    $editor = new TextEditor('editor', '');
    $editor->setFrame(new Rect(0, 0, 8, 1));
    $buffer = new GridBuffer(8, 1);
    $editor->render($buffer);
    assertSame('¶', $buffer->cell(0, 0)?->glyph, 'Empty editor cursor should render a visible glyph.');
    assertSame('#aaaaaa', $buffer->cell(0, 0)?->fg->hex(), 'Unfocused editor cursor should use the editor text foreground.');
    assertSame('#555555', $buffer->cell(0, 0)?->bg->hex(), 'Unfocused editor cursor should use dark gray background.');

    $editor->setFocused(true);
    $buffer = new GridBuffer(8, 1);
    $editor->render($buffer);
    assertSame('¶', $buffer->cell(0, 0)?->glyph, 'Focused empty editor cursor should render a visible glyph.');
    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused editor cursor should use white foreground.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused editor cursor should use black background.');

    $editor = new TextEditor('editor', 'abc');
    $editor->setFrame(new Rect(0, 0, 8, 1));
    $editor->setFocused(true);
    $buffer = new GridBuffer(8, 1);
    $editor->render($buffer);
    assertSame('a', $buffer->cell(0, 0)?->glyph, 'Editor cursor should preserve the character under it.');
    assertSame('#ffffff', $buffer->cell(0, 0)?->fg->hex(), 'Focused editor text cursor should use white foreground.');
    assertSame('#000000', $buffer->cell(0, 0)?->bg->hex(), 'Focused editor text cursor should use black background.');
  },

  'cursor colors come from theme' => function(): void {
    $theme = new Theme(
      cursorFg: '#123456',
      cursorBg: '#234567',
      inactiveCursorFg: '#345678',
      inactiveCursorBg: '#456789'
    );

    $editor = new TextEditor('editor', '');
    $editor->setTheme($theme);
    $editor->setFrame(new Rect(0, 0, 4, 1));
    $buffer = new GridBuffer(4, 1);
    $editor->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(0, 0)?->fg->hex(), 'Inactive editor cursor should use the editor text foreground.');
    assertSame('#456789', $buffer->cell(0, 0)?->bg->hex(), 'Inactive editor cursor should use theme background.');

    $editor->setFocused(true);
    $buffer = new GridBuffer(4, 1);
    $editor->render($buffer);
    assertSame('#123456', $buffer->cell(0, 0)?->fg->hex(), 'Focused editor cursor should use theme foreground.');
    assertSame('#234567', $buffer->cell(0, 0)?->bg->hex(), 'Focused editor cursor should use theme background.');

    $editor = new TextEditor('editor', '');
    $editor->setFieldColors('#111111', '#dddddd');
    $editor->setFrame(new Rect(0, 0, 4, 1));
    $buffer = new GridBuffer(4, 1);
    $editor->render($buffer);
    assertSame('#111111', $buffer->cell(0, 0)?->fg->hex(), 'Inactive editor cursor should follow a dark field foreground.');
  },

  'text editor plain text uses theme colors' => function(): void {
    $theme = new Theme(fg: '#aaaaaa', bg: '#000088', inverseBg: '#00aaaa');
    $editor = new TextEditor('editor', 'abc');
    $editor->setTheme($theme);
    $editor->setFrame(new Rect(0, 0, 4, 2));
    $buffer = new GridBuffer(4, 2);
    $editor->render($buffer);

    assertSame('#aaaaaa', $buffer->cell(1, 0)?->fg->hex(), 'Plain editor text should use the inherited theme foreground.');
    assertSame('#000088', $buffer->cell(1, 0)?->bg->hex(), 'Plain editor text should use the inherited theme background.');
    assertSame('#000088', $buffer->cell(0, 1)?->bg->hex(), 'Empty editor space should use the inherited theme background.');

    $editor->setFocused(true);
    $buffer = new GridBuffer(4, 2);
    $editor->render($buffer);

    assertSame('#0000a3', $buffer->cell(1, 0)?->bg->hex(), 'Focused editor text should brighten the inherited theme background.');
    assertSame('#0000a3', $buffer->cell(0, 1)?->bg->hex(), 'Focused editor empty space should brighten the inherited theme background.');
  },

  'focused text editor brightens panel field background' => function(): void {
    $editor = new TextEditor('editor', 'abc');
    $editor->setFieldColors('#000000', '#338833');
    $editor->setFrame(new Rect(0, 0, 4, 2));
    $editor->setFocused(true);
    $buffer = new GridBuffer(4, 2);
    $editor->render($buffer);

    assertSame('#3da33d', $buffer->cell(1, 0)?->bg->hex(), 'Focused panel editor text should brighten the green field background.');
    assertSame('#3da33d', $buffer->cell(0, 1)?->bg->hex(), 'Focused panel editor empty space should brighten the green field background.');
  },

  'text renderer joins buffer lines' => function(): void {
    $buffer = new GridBuffer(3, 2);
    $buffer->write(0, 0, 'abc');
    $renderer = new TextRenderer();
    assertSame("abc\n   \n", $renderer->render($buffer), 'Text renderer should serialize grid lines.');
  },

  'later elements cover earlier decorations' => function(): void {
    $root = new class('overlay') extends Element {
      protected function paint(RenderTarget $target): void {
        ;
      }
    };
    $root->add(new DialogBox('base', true, 'Base'));
    $root->add(new DialogBox('top', true, 'Top'));
    $root->setFrame(new Rect(0, 0, 16, 4));
    $buffer = new GridBuffer(16, 4);
    $root->render($buffer);
    assertSame('+ Top ---------+', $buffer->line(0), 'Later dialog box should cover the earlier dialog box title and border.');
  },
];
