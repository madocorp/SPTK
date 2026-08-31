<?php

namespace SPTK\Tests;

use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\Dock;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\MenuPopup;
use SPTK\Widgets\StatusBar;
use SPTK\Widgets\TextEditor;

return [
  'menubar moves cursor with arrows' => function(): void {
    $menu = new MenuBar('menu', ['File', 'Edit', 'View']);
    $menu->handle(InputEvent::key('Right'));
    assertSame(1, $menu->cursor(), 'Right should move to next menu item.');
    $menu->handle(InputEvent::key('Left'));
    assertSame(0, $menu->cursor(), 'Left should move to previous menu item.');
  },

  'menubar activates selected callback from function key' => function(): void {
    $status = new StatusBar('status', 'idle');
    $menu = new MenuBar('menu', [
      'File',
      [
        'label' => 'Edit',
        'action' => function() use ($status): void {
          $status->setText('activated');
        },
      ],
    ]);
    $root = new Dock('root');
    $root->dock($menu, 'top');
    $root->dock($status, 'bottom');
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F2'));
    assertSame('activated', $status->text(), 'F2 should activate the selected item callback.');
  },

  'menubar renders hotkey colors' => function(): void {
    $menu = new MenuBar('menu', ['File']);
    $menu->setFrame(new Rect(0, 0, 12, 1));
    new FocusManager($menu);
    $buffer = new GridBuffer(12, 1);
    $menu->render($buffer);
    assertSame('  1 File    ', $buffer->line(0), 'Menu should render one leading space before the first item.');
    assertSame('#0000ff', $buffer->cell(2, 0)?->fg->hex(), 'Hotkey should render blue.');
    assertSame('#000000', $buffer->cell(4, 0)?->fg->hex(), 'Label should remain black.');
    assertSame('#00aaaa', $buffer->cell(4, 0)?->bg->hex(), 'Menu should not render as active when no popup is open.');
  },

  'menubar focused cursor keeps menu highlight colors' => function(): void {
    $menu = new MenuBar('menu', ['File']);
    $menu->setFrame(new Rect(0, 0, 12, 1));
    $menu->setFocused(true);
    $buffer = new GridBuffer(12, 1);
    $menu->render($buffer);
    assertSame('#000000', $buffer->cell(4, 0)?->fg->hex(), 'Focused menu cursor should keep menu foreground.');
    assertSame('#00ffff', $buffer->cell(4, 0)?->bg->hex(), 'Focused menu cursor should keep menu highlight background.');
  },

  'menubar function key activates item globally' => function(): void {
    $status = new StatusBar('status', 'idle');
    $editor = new TextEditor('editor', '');
    $menu = new MenuBar('menu', [
      'File',
      [
        'label' => 'Edit',
        'action' => function() use ($status): void {
          $status->setText('f2');
        },
      ],
    ]);
    $root = new Dock('root');
    $root->fill($editor);
    $root->dock($menu, 'top');
    $root->dock($status, 'bottom');
    $focus = new FocusManager($root);
    assertSame('editor', $focus->current()?->name(), 'Editor should be focused first in this fixture.');
    $focus->dispatch(InputEvent::key('F2'));
    assertSame('f2', $status->text(), 'F2 should activate the second menu item without menu focus.');
  },

  'menubar runs on open before creating popup' => function(): void {
    $project = new MenuItem([
      'label' => 'Project',
      'onOpen' => function(MenuItem $item): void {
        $item->update(['items' => ['Alpha', 'Beta']]);
      },
    ]);
    $menu = new MenuBar('menu', [$project]);
    $root = new Dock('root');
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));

    assertSame(true, $menu->openItem(0), 'Opening a top-level item should succeed.');
    assertInstanceOf(MenuPopup::class, $root->children()[1] ?? null, 'Opening a submenu should create a popup.');
    assertSame('Alpha', $root->children()[1]->item(0)?->label(), 'Popup should use items updated by the on-open callback.');
  },

  'menubar refreshes open popup without moving parent cursor' => function(): void {
    $filters = new MenuItem([
      'label' => 'Filters',
      'items' => [
        ['label' => 'JQL'],
        ['label' => 'Search'],
        ['label' => 'Assignee', 'items' => ['Alice', 'Bob']],
      ],
    ]);
    $menu = new MenuBar('menu', [$filters]);
    $root = new Dock('root');
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    new FocusManager($root);

    assertSame(true, $menu->openItem(0), 'Opening the filters menu should succeed.');
    $popup = $root->children()[1] ?? null;
    assertInstanceOf(MenuPopup::class, $popup, 'Opening a submenu should create a popup.');
    $popup->handle(InputEvent::key('Down'));
    $popup->handle(InputEvent::key('Down'));
    $popup->handle(InputEvent::key('Right'));
    assertSame(2, $popup->cursor(), 'Fixture should place the cursor on the Assignee row.');
    $child = $root->children()[2] ?? null;
    assertInstanceOf(MenuPopup::class, $child, 'Opening a child submenu should create a child popup.');

    $filters->update([
      'items' => [
        ['label' => 'JQL'],
        ['label' => 'Search'],
        [
          'label' => 'Assignee',
          'checked' => true,
          'items' => [
            ['label' => 'Alice'],
            ['label' => 'Bob', 'checked' => true],
          ],
        ],
      ],
    ]);

    assertSame(true, $menu->refreshOpenPopup(), 'Refreshing an open popup should succeed.');
    assertSame(2, $popup->cursor(), 'Refreshing should not move the parent popup cursor.');
    assertSame(true, $popup->item(2)?->checked(), 'Refreshing should update parent row markers.');
    assertInstanceOf(MenuPopup::class, $root->children()[2] ?? null, 'Refreshing should leave the child submenu open.');
    assertSame($child, $root->children()[2], 'Refreshing should preserve the child popup instance.');
    assertSame(0, $child->cursor(), 'Refreshing should not move the child popup cursor.');
    assertSame(true, $child->item(1)?->checked(), 'Refreshing should update child row markers.');
  },

  'menubar rejects public open for non submenu items' => function(): void {
    $menu = new MenuBar('menu', ['Project']);

    assertSame(false, $menu->openItem(0), 'Opening a top-level item without submenu rows should fail.');
    assertSame(false, $menu->openItem(3), 'Opening a missing top-level item should fail.');
  },
];
