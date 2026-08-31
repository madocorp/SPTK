<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\Theme;
use SPTK\Widgets\ListItem;
use SPTK\Widgets\ListView;

return [
  'list view keeps centered grid alignment by default' => function(): void {
    $list = new ListView('list', ['alpha']);
    assertSame('center', $list->gridAlignment(), 'Main-screen lists should keep the default centered grid alignment.');
  },

  'list view normalizes rows and reports active value' => function(): void {
    $list = new ListView('list', [
      'alpha',
      ['value' => 2, 'text' => 'beta'],
    ], ['paddingX' => 0]);
    assertInstanceOf(ListItem::class, $list->item(0), 'String rows should be normalized into ListItem objects.');
    assertSame('alpha', $list->value(), 'The active string row should use its text as value.');
    $list->handle(InputEvent::key('Down'));
    assertSame(2, $list->value(), 'Array rows should preserve explicit values.');
    $list->addItem('gamma', 1);
    assertSame('gamma', $list->item(1)?->text(), 'Inserted rows should be placed at the requested index.');
  },

  'list view handles key aliases through input actions' => function(): void {
    $list = new ListView('list', ['alpha', 'beta']);
    $list->handle(InputEvent::key('KeypadDown'));
    assertSame(1, $list->cursor(), 'Keypad Down should move the list cursor.');
  },

  'list view renders adornments reserves and truncation' => function(): void {
    $list = new ListView('list', [
      ['text' => 'alphabet', 'left' => '>', 'leftReserve' => 2, 'prefix' => 'db', 'right' => 'R', 'truncateMarker' => '~'],
      ['text' => 'beta', 'rightReserve' => 3],
    ], ['paddingX' => 0]);
    $list->setFrame(new Rect(0, 0, 12, 2));
    $buffer = new GridBuffer(12, 2);
    $list->render($buffer);
    assertSame('> db alp~  R', $buffer->line(0), 'List rows should render left, prefix, truncated text, and reserved right adornment.');
    assertSame('#00ffff', $buffer->cell(0, 0)?->fg->hex(), 'Left adornment should use the marker color.');
    assertSame('#00ffff', $buffer->cell(11, 0)?->fg->hex(), 'Right adornment should use the marker color.');
  },

  'list view renders horizontal padding by default' => function(): void {
    $list = new ListView('list', [
      ['text' => 'alpha', 'right' => 'R'],
    ]);
    $list->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $list->render($buffer);

    assertSame(' alpha  R ', $buffer->line(0), 'Horizontal padding should inset list content from both sides.');
  },

  'focused list view brightens field background' => function(): void {
    $list = new ListView('list', ['alpha', 'beta'], ['paddingX' => 0]);
    $list->setTheme(new Theme(bg: '#338833'));
    $list->setFrame(new Rect(0, 0, 8, 2));
    $list->setFocused(true);
    $buffer = new GridBuffer(8, 2);
    $list->render($buffer);

    assertSame('#3da33d', $buffer->cell(0, 1)?->bg->hex(), 'Focused panel list field rows should brighten the green background.');
  },

  'list view supports search and filtering' => function(): void {
    $list = new ListView('list', [
      ['text' => 'Alpha', 'filterable' => true],
      ['text' => 'Beta', 'filterable' => true],
      ['text' => 'Alpine', 'filterable' => true],
    ], ['filterable' => true, 'paddingX' => 0]);
    $list->setFrame(new Rect(0, 0, 10, 3));
    $list->handle(InputEvent::text('A'));
    $list->handle(InputEvent::text('l'));
    assertSame(0, $list->cursor(), 'Search should move to the first matching row.');
    $buffer = new GridBuffer(10, 3);
    $list->render($buffer);
    assertSame('Alpha     ', $buffer->line(0), 'Filter mode should keep matching rows visible.');
    assertSame('Alpine    ', $buffer->line(1), 'Filter mode should hide nonmatching rows.');
    assertSame('#ffff00', $buffer->cell(0, 0)?->bg->hex(), 'Search match should be highlighted.');
    $list->handle(InputEvent::key('Backspace'));
    $list->handle(InputEvent::key('Delete'));
    $buffer = new GridBuffer(10, 3);
    $list->render($buffer);
    assertSame('Beta      ', $buffer->line(1), 'Delete should clear filtering and reveal all rows.');
  },

  'list view handles selection modes' => function(): void {
    $list = new ListView('list', [
      ['value' => 'a', 'selectable' => true],
      ['value' => 'b', 'selectable' => true],
      ['value' => 'c', 'selectable' => 'group'],
      ['value' => 'd', 'selectable' => 'group'],
    ], ['selectionOrder' => true]);
    $list->handle(InputEvent::key('Space'));
    $list->handle(InputEvent::key('Down'));
    $list->handle(InputEvent::key('Space'));
    assertSame(['a', 'b'], $list->selectedValues(), 'Multi-selection should return selected values in selection order.');
    assertSame('1', $list->item(0)?->left(), 'Ordered selection should mark the first selected item.');
    assertSame('2', $list->item(1)?->left(), 'Ordered selection should mark the second selected item.');
    $list->handle(InputEvent::key('Down'));
    $list->handle(InputEvent::key('Space'));
    $list->handle(InputEvent::key('Down'));
    $list->handle(InputEvent::key('Space'));
    assertSame(false, $list->item(2)?->selected(), 'Radio-style selection should deselect the previous group item.');
    assertSame(true, $list->item(3)?->selected(), 'Radio-style selection should select the active group item.');
    $list->clearSelection();
    assertSame([], $list->selectedValues(), 'clearSelection should deselect selectable rows.');
  },

  'list view can reorder rows with shift arrows' => function(): void {
    $list = new ListView('list', [
      ['value' => 'a', 'text' => 'Alpha'],
      ['value' => 'b', 'text' => 'Beta'],
      ['value' => 'c', 'text' => 'Gamma'],
    ], ['reorderable' => true]);
    $list->handle(InputEvent::key('Down'));
    $list->handle(InputEvent::key('Down', ['shift' => true]));
    assertSame(['a', 'c', 'b'], $list->values(), 'Shift+Down should move the active row down.');
    assertSame(2, $list->cursor(), 'Moved row should remain active after moving down.');
    $list->handle(InputEvent::key('Up', ['shift' => true]));
    assertSame(['a', 'b', 'c'], $list->values(), 'Shift+Up should move the active row up.');
    assertSame(1, $list->cursor(), 'Moved row should remain active after moving up.');
  },

  'list view separates selectable marker from text' => function(): void {
    $list = new ListView('list', [
      ['text' => 'main', 'selectable' => 'database', 'selected' => true],
      ['text' => 'analytics', 'selectable' => 'database'],
    ], ['paddingX' => 0]);
    $list->setFrame(new Rect(0, 0, 12, 2));
    $buffer = new GridBuffer(12, 2);
    $list->render($buffer);

    assertSame('* main      ', $buffer->line(0), 'Selected radio-style rows should leave a space between marker and text.');
    $list->handle(InputEvent::key('Down'));
    $list->handle(InputEvent::key('Space'));
    assertSame(true, $list->item(1)?->selected(), 'Space should select a selectable row.');
    $list->handle(InputEvent::key('Space'));
    assertSame(false, $list->item(1)?->selected(), 'Space on a selected radio-style row should deselect it.');
  },

  'list view scrolls and pages through visible rows' => function(): void {
    $list = new ListView('list', ['one', 'two', 'three', 'four'], ['paddingX' => 0]);
    $list->setFrame(new Rect(0, 0, 8, 2));
    $list->handle(InputEvent::key('PageDown'));
    assertSame(1, $list->cursor(), 'PageDown should move by visible rows minus one.');
    $list->handle(InputEvent::key('PageDown', ['ctrl' => true]));
    assertSame(3, $list->cursor(), 'Ctrl+PageDown should move to the final row.');
    $list->handle(InputEvent::key('PageUp'));
    assertSame(2, $list->cursor(), 'PageUp should move by visible rows minus one.');
    $list->handle(InputEvent::key('PageUp', ['ctrl' => true]));
    assertSame(0, $list->cursor(), 'Ctrl+PageUp should move to the first row.');
    $list->handle(InputEvent::key('End'));
    assertSame(3, $list->cursor(), 'End should move to the final row.');
    $buffer = new GridBuffer(8, 2);
    $list->render($buffer);
    assertSame('three   ', $buffer->line(0), 'Scroll should keep the cursor visible at the bottom.');
    assertSame('four    ', $buffer->line(1), 'The final row should be rendered after scrolling.');
  },

  'list view resyncs scroll after frame height changes' => function(): void {
    $list = new ListView('list', ['one', 'two', 'three', 'four', 'five'], ['paddingX' => 0]);
    $list->setFrame(new Rect(0, 0, 8, 4));
    $list->handle(InputEvent::key('End'));
    $list->setFrame(new Rect(0, 0, 8, 2));

    $buffer = new GridBuffer(8, 2);
    $list->render($buffer);

    assertSame('four    ', $buffer->line(0), 'Scroll should update when the visible frame shrinks.');
    assertSame('five    ', $buffer->line(1), 'The active cursor row should remain visible after relayout.');
  },
];
