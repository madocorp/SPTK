<?php

namespace SPTK\Tests;

use SPTK\Core\FocusManager;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\ElementContext;
use SPTK\Core\RenderTarget;
use SPTK\Widgets\Dock;
use SPTK\Widgets\DialogBox;
use SPTK\Widgets\ListView;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\TextEditor;
use SPTK\Widgets\WorkspaceBox;

return [
  'focus manager cycles focusable elements' => function(): void {
    $root = new Dock('root');
    $list = new ListView('list', ['one', 'two']);
    $editor = new TextEditor('editor', '');
    $root->dock($list, 'left', ['ratio' => 0.5, 'separator' => true])->fill($editor);
    $root->setFrame(new Rect(0, 0, 20, 5));
    $focus = new FocusManager($root);
    assertSame('list', $focus->current()?->name(), 'First focusable element should be focused.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('editor', $focus->current()?->name(), 'Tab should advance focus.');
  },

  'focused element receives input' => function(): void {
    $root = new Dock('root');
    $list = new ListView('list', ['one', 'two', 'three']);
    $editor = new TextEditor('editor', '');
    $root->dock($list, 'left', ['ratio' => 0.5, 'separator' => true])->fill($editor);
    $root->setFrame(new Rect(0, 0, 20, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('Down'));
    assertSame(1, $list->cursor(), 'Key events should reach focused list.');
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::text('x'));
    assertSame('x', $editor->text(), 'Text events should reach focused editor.');
  },

  'focused element receives key release without shortcut handling' => function(): void {
    $root = new Dock('root');
    $releaseTarget = new class('release-target') extends \SPTK\Core\Element {
      public array $events = [];

      public function __construct(string $name) {
        parent::__construct($name);
        $this->focusable = true;
      }

      public function handle(InputEvent $event): bool {
        $this->events[] = [$event->type, $event->key, $event->modifiers];
        return true;
      }

      protected function paint(RenderTarget $target): void {
      }
    };
    $root->fill($releaseTarget);
    $root->setFrame(new Rect(0, 0, 20, 5));
    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::keyRelease('Left', ['shift' => true])), 'Key release should dispatch to the focused element.');
    assertSame([
      ['key-release', 'Left', ['shift' => true]],
    ], $releaseTarget->events, 'Key release should preserve key name and modifiers.');
  },

  'menu bar is skipped by tab focus' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', ['File']);
    $editor = new TextEditor('editor', '');
    $root->dock($menu, 'top');
    $root->fill($editor);
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    assertSame('editor', $focus->current()?->name(), 'MenuBar should not be part of the focus list.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('editor', $focus->current()?->name(), 'Tab should continue to skip the menu bar.');
  },

  'shift tab moves focus backward' => function(): void {
    $root = new Dock('root');
    $list = new ListView('list', ['one', 'two']);
    $editor = new TextEditor('editor', '');
    $root->dock($list, 'left', ['ratio' => 0.5, 'separator' => true])->fill($editor);
    $root->setFrame(new Rect(0, 0, 20, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('Tab', ['shift' => true]));
    assertSame('editor', $focus->current()?->name(), 'Shift+Tab should move to the previous focusable element.');
  },

  'dialog box exposes all descendants in add order' => function(): void {
    $dialog = new DialogBox('dialog');
    $list = new ListView('list', ['one', 'two']);
    $editor = new TextEditor('editor', '');
    $dialog->add($list)->add($editor);
    $dialog->setFrame(new Rect(0, 0, 20, 5));
    $focus = new FocusManager($dialog);
    assertSame('list', $focus->current()?->name(), 'Dialog should focus its first descendant.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('editor', $focus->current()?->name(), 'Dialog Tab order should follow add order.');
  },

  'workspace box is one focus stop and remembers descendant focus' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $left = new WorkspaceBox('left');
    $right = new WorkspaceBox('right');
    $leftList = new ListView('left-list', ['one']);
    $leftEditor = new TextEditor('left-editor', '');
    $rightEditor = new TextEditor('right-editor', '');
    $left->add($leftList)->add($leftEditor);
    $right->add($rightEditor);
    $root->dock($left, 'left', ['ratio' => 0.5, 'separator' => true])->fill($right);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 5));
    $focus = new FocusManager($root);
    assertSame('left-list', $focus->current()?->name(), 'Workspace should initially expose its first focusable child.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('right-editor', $focus->current()?->name(), 'Tab should move to the next workspace.');
    $leftEditor->requestFocus();
    $focus->rebuild($context->takeRequestedFocus());
    assertSame('left-editor', $focus->current()?->name(), 'A direct request should update the workspace remembered focus.');
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('left-editor', $focus->current()?->name(), 'Returning to a workspace should restore its remembered child.');
  },

  'escape from menu popup restores previous focus' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $editor = new TextEditor('editor', '');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->fill($editor);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 6));
    $focus = new FocusManager($root);
    assertSame('editor', $focus->current()?->name(), 'Editor should be focused before opening the menu.');
    $focus->dispatch(InputEvent::key('F1'));
    $focus->rebuild($context->takeRequestedFocus());
    assertSame('menu-popup', $focus->current()?->name(), 'Popup should receive focus when opened.');
    $focus->dispatch(InputEvent::key('Escape'));
    $focus->rebuild($context->takeRequestedFocus());
    assertSame('editor', $focus->current()?->name(), 'Closing a popup should restore the previous non-popup focus.');
  },

  'selected menubar action item captures text focus' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $editor = new TextEditor('editor', '');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
        'action' => function(): void {
        },
      ],
    ]);
    $root->fill($editor);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 6));
    $focus = new FocusManager($root);
    assertSame('editor', $focus->current()?->name(), 'Editor should start focused.');
    $focus->dispatch(InputEvent::key('F1'));
    $focus->rebuild($context->takeRequestedFocus());
    $menu->activateNextTopItem();
    $focus->rebuild($context->takeRequestedFocus());
    assertSame('menu', $focus->current()?->name(), 'Selected action-only top item should focus the menubar.');
    $focus->dispatch(InputEvent::text('x'));
    assertSame('', $editor->text(), 'Text should not be written into the background editor while menu item is selected.');
  },

  'menubar action restores focus before callback' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $editor = new TextEditor('editor', '');
    $focus = null;
    $callbackFocus = '';
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
        'action' => function() use (&$focus, &$callbackFocus): void {
          $callbackFocus = $focus?->current()?->name() ?? '';
        },
      ],
    ]);
    $root->fill($editor);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 6));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $menu->activateNextTopItem();
    $focus->rebuild($context->takeRequestedFocus());
    assertSame('menu', $focus->current()?->name(), 'Menu should own focus before action activation.');
    $focus->dispatch(InputEvent::key('Enter'));
    assertSame('editor', $callbackFocus, 'Action callback should run after focus has returned to the previous element.');
    assertSame('editor', $focus->current()?->name(), 'Focus should remain on the previous element after action dispatch.');
  },
];
