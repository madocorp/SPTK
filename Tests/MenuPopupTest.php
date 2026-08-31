<?php

namespace SPTK\Tests;

use SPTK\Core\FocusManager;
use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;
use SPTK\Widgets\Dock;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\MenuPopup;
use SPTK\Widgets\StatusBar;

return [
  'menu popup measures adornment columns from layout hints' => function(): void {
    $popup = new MenuPopup('popup', [
      ['label' => 'users', 'right' => 'ASC'],
      ['label' => 'orders', 'left' => 'V', 'right' => 'DESC'],
    ], [
      'leftValues' => ['', 'V', '!'],
      'rightValues' => ['ASC', 'DESC'],
    ]);
    assertSame(15, $popup->preferredWidth(), 'Popup width should reserve hinted left and right values.');
  },

  'menu popup renders aligned adornments' => function(): void {
    $popup = new MenuPopup('popup', [
      ['label' => 'users', 'right' => 'ASC'],
      ['label' => 'orders', 'left' => 'V', 'right' => 'DESC'],
      ['label' => 'items', 'right' => 'ASC'],
    ], [
      'leftValues' => ['', 'V'],
      'rightValues' => ['ASC', 'DESC'],
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $buffer = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($buffer);
    assertSame('+-------------+', $buffer->line(0), 'Headless popup should approximate the top border.');
    assertSame('|V orders DESC|', $buffer->line(1), 'Headless popup should approximate vertical borders without extra cells.');
    assertSame('#0000ff', $buffer->cell(1, 1)?->fg->hex(), 'Left adornment should be blue.');
    assertSame('#0000ff', $buffer->cell(10, 1)?->fg->hex(), 'Right adornment should be blue.');
  },

  'menu popup uses sptk default colors' => function(): void {
    $popup = new MenuPopup('popup', ['Open', 'Save', 'Exit']);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    $normal = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($normal);
    assertSame('#ffffff', $normal->cell(1, 1)?->fg->hex(), 'Popup text should render white by default.');
    assertSame('#00aaaa', $normal->cell(1, 1)?->bg->hex(), 'Popup rows should render on darker cyan by default.');

    new FocusManager($popup);
    $popup->handle(InputEvent::key('Down'));
    $active = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($active);
    assertSame('#ffffff', $active->cell(1, 1)?->fg->hex(), 'Popup cursor should render white text.');
    assertSame('#000000', $active->cell(1, 1)?->bg->hex(), 'Popup cursor should render black background.');
  },

  'menu popup focuses checked item on open' => function(): void {
    $popup = new MenuPopup('popup', [
      ['label' => 'Slide 1'],
      ['label' => 'Slide 2', 'checked' => true],
      ['label' => 'Slide 3'],
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    $popup->focusOnOpen();
    assertSame(1, $popup->cursor(), 'Opening a popup should activate the checked row.');
  },

  'menu popup renders separators between rows' => function(): void {
    $popup = new MenuPopup('popup', [
      new MenuItem([
        'label' => 'Open',
        'separatorAfter' => true,
      ]),
      new MenuItem('Close'),
      new MenuItem('Exit'),
    ]);
    assertSame(3, $popup->preferredHeight(), 'Separator should not reserve its own popup row.');
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $buffer = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($buffer);
    assertSame('+-----+', $buffer->line(0), 'First item row should still render the popup top border.');
    assertSame('|-----|', $buffer->line(1), 'Headless rendering should approximate the separator at the next row boundary.');

    $target = new class implements RenderTarget {
      public array $lines = [];

      public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
        $this->lines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
      }

      public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function pushClip(Rect $rect): void {
      }

      public function popClip(): void {
      }
    };
    $popup->render($target);
    assertSame([0.0, 1.0, 7.0, 1.0, '#0000ff', 2], $target->lines[0], 'Separator should draw as a blue two-pixel line between item rows.');
  },

  'searchable menu highlights prefix matches and jumps to first match' => function(): void {
    $popup = new MenuPopup('popup', [
      new MenuItem('Alpha'),
      new MenuItem('Beta'),
      new MenuItem('Alpine'),
    ], [
      'searchable' => true,
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $popup->handle(InputEvent::text('b'));
    assertSame(1, $popup->cursor(), 'Search should jump to the first prefix match.');
    $buffer = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($buffer);
    assertSame('#000000', $buffer->cell(1, 1)?->fg->hex(), 'Matched text should render black.');
    assertSame('#ffff00', $buffer->cell(1, 1)?->bg->hex(), 'Matched text should render on yellow.');
    $popup->handle(InputEvent::key('Backspace'));
    $popup->handle(InputEvent::text('a'));
    $popup->handle(InputEvent::text('l'));
    $popup->handle(InputEvent::text('p'));
    assertSame(0, $popup->cursor(), 'Search should keep the cursor on the first matching prefix row.');
    $popup->handle(InputEvent::key('Down'));
    assertSame(1, $popup->cursor(), 'Search mode should still allow normal row navigation.');
  },

  'filterable menu shows only matches and supports contains operator' => function(): void {
    $popup = new MenuPopup('popup', [
      new MenuItem('Alpha'),
      new MenuItem('Beta'),
      new MenuItem('Gamma'),
      new MenuItem('Delta'),
    ], [
      'filterable' => true,
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $popup->handle(InputEvent::text('*'));
    $popup->handle(InputEvent::text('ta'));
    assertSame(1, $popup->cursor(), 'Contains filter should jump to the first anywhere match.');
    $target = new class implements RenderTarget {
      public array $labels = [];

      public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
        if (trim($text) !== '') {
          $this->labels[$y] = ($this->labels[$y] ?? '') . trim($text);
        }
      }

      public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function pushClip(Rect $rect): void {
      }

      public function popClip(): void {
      }
    };
    $popup->render($target);
    assertSame(['Beta', 'Delta'], array_values($target->labels), 'Filter should render only matching rows.');
    $popup->handle(InputEvent::key('Backspace'));
    $popup->handle(InputEvent::key('Backspace'));
    $popup->handle(InputEvent::text('g'));
    assertSame(2, $popup->cursor(), 'Backspacing an empty search should reset contains mode to prefix matching.');
  },

  'menu search ignores characters that would produce no matches' => function(): void {
    $popup = new MenuPopup('popup', [
      new MenuItem('Alpha'),
      new MenuItem('Alpine'),
      new MenuItem('Beta'),
    ], [
      'searchable' => true,
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $popup->handle(InputEvent::text('a'));
    $popup->handle(InputEvent::text('z'));
    $popup->handle(InputEvent::key('Backspace'));
    $popup->handle(InputEvent::text('b'));
    assertSame(2, $popup->cursor(), 'Rejected search characters should not remain hidden in the search buffer.');
  },

  'menubar opens submenu popup as dynamic root child' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->dock(new StatusBar('status', 'ready'), 'bottom');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    assertSame(3, count($root->children()), 'Opening a submenu should add a popup to the root tree.');
    assertInstanceOf(MenuPopup::class, $root->children()[2], 'The added root child should be a menu popup.');
    $popup = $root->children()[2];
    $frame = $popup->frame();
    $root->setFrame(new Rect(0, 0, 30, 5));
    assertSame([$frame->x, $frame->y, $frame->width, $frame->height], [$popup->frame()->x, $popup->frame()->y, $popup->frame()->width, $popup->frame()->height], 'Root relayout should not expand an absolute menu popup.');
  },

  'popup action closes and restores focus before callback' => function(): void {
    $context = new \SPTK\Core\ElementContext();
    $root = new Dock('root');
    $editor = new \SPTK\Widgets\TextEditor('editor', '');
    $focus = null;
    $callbackFocus = '';
    $callbackChildren = 0;
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Open',
            'action' => function() use (&$focus, &$root, &$callbackFocus, &$callbackChildren): void {
              $callbackFocus = $focus?->current()?->name() ?? '';
              $callbackChildren = count($root->children());
            },
          ],
        ],
      ],
    ]);
    $root->fill($editor);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    assertInstanceOf(MenuPopup::class, $focus->current(), 'Popup should receive focus when opened.');
    $focus->dispatch(InputEvent::key('Enter'));
    assertSame('editor', $callbackFocus, 'Popup action should run after focus returns to the previous element.');
    assertSame(2, $callbackChildren, 'Popup action should run after the popup is removed from the root tree.');
    $buffer = new GridBuffer(30, 5);
    $root->render($buffer);
    assertSame('#00aaaa', $buffer->cell(3, 0)?->bg->hex(), 'Menubar item should not stay highlighted after submenu action closes.');
  },

  'menubar opens submenu popup with down key' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $menu->handle(InputEvent::key('Down'));
    assertSame(2, count($root->children()), 'Down should open the selected top-level submenu.');
    assertInstanceOf(MenuPopup::class, $root->children()[1], 'Down should add a popup for submenu items.');
  },

  'menubar keeps submenu at least as wide as top label' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'Connection',
        'items' => [
          ['label' => 'abc'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening submenu should add a popup.');
    assertSame(12, $popup->frame()->width, 'Popup content should reserve the top-level label width plus row padding.');
  },

  'menubar caps long popup height and adds scrollbar column' => function(): void {
    $root = new Dock('root');
    $items = [];
    for ($i = 1; $i <= 10; $i++) {
      $items[] = ['label' => "Item {$i}"];
    }
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'layout' => [
          'maxHeightRatio' => 0.5,
        ],
        'items' => $items,
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 10));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening long submenu should add a popup.');
    assertSame(5, $popup->frame()->height, 'Long popup height should be capped to the configured row ratio.');
    assertSame($popup->preferredWidth() + 1, $popup->frame()->width, 'Scrollable popup should reserve one scrollbar column.');
  },

  'menu popup scrolls with blue triangle decorations' => function(): void {
    $items = [];
    for ($i = 1; $i <= 7; $i++) {
      $items[] = ['label' => "Item {$i}"];
    }
    $popup = new MenuPopup('popup', $items);
    $popup->setFrame(new Rect(0, 0, $popup->framedWidthForHeight(3), 3));
    $target = new class implements RenderTarget {
      public array $triangles = [];

      public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawFillTriangle(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, string|int|Color $color): void {
        $this->triangles[] = [$x1, $y1, $x2, $y2, $x3, $y3, Color::from($color)->hex()];
      }

      public function pushClip(Rect $rect): void {
      }

      public function popClip(): void {
      }
    };
    $popup->render($target);
    assertSame([[8.0, 3.0, 9.0, 3.0, 9.0, 1.0, '#0000ff']], $target->triangles, 'Initial scroll state should show the lower SPTK-style triangle below the handle.');
    for ($i = 0; $i < 5; $i++) {
      $popup->handle(InputEvent::key('Down'));
    }
    $target->triangles = [];
    $popup->render($target);
    assertSame([
      [8.0, 0.0, 9.0, 0.0, 9.0, 1.0, '#0000ff'],
      [8.0, 3.0, 9.0, 3.0, 9.0, 2.0, '#0000ff'],
    ], $target->triangles, 'Scrolled popup should show SPTK-style triangles above and below the proportional handle.');
  },

  'menu popup supports page movement keys' => function(): void {
    $items = [];
    for ($i = 1; $i <= 10; $i++) {
      $items[] = ['label' => "Item {$i}"];
    }
    $popup = new MenuPopup('popup', $items);
    $popup->setFrame(new Rect(0, 0, $popup->framedWidthForHeight(4), 4));

    assertSame(true, $popup->handle(InputEvent::key('PageDown')), 'PageDown should be handled.');
    assertSame(3, $popup->cursor(), 'PageDown should move by the visible page minus one row.');
    assertSame(true, $popup->handle(InputEvent::key('PageDown', ['ctrl' => true])), 'Ctrl+PageDown should be handled.');
    assertSame(9, $popup->cursor(), 'Ctrl+PageDown should move to the last visible item.');
    assertSame(true, $popup->handle(InputEvent::key('PageUp')), 'PageUp should be handled.');
    assertSame(6, $popup->cursor(), 'PageUp should move by the visible page minus one row.');
    assertSame(true, $popup->handle(InputEvent::key('PageUp', ['ctrl' => true])), 'Ctrl+PageUp should be handled.');
    assertSame(0, $popup->cursor(), 'Ctrl+PageUp should move to the first visible item.');
  },

  'horizontal scrollbar uses cell-width height aligned bottom' => function(): void {
    $target = new class implements RenderTarget {
      public array $triangles = [];

      public function scrollbarThickness(string $orientation): float {
        return $orientation === 'horizontal' ? 0.4 : 1.0;
      }

      public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawFillTriangle(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, string|int|Color $color): void {
        $this->triangles[] = [$x1, $y1, $x2, $y2, $x3, $y3, Color::from($color)->hex()];
      }

      public function pushClip(Rect $rect): void {
      }

      public function popClip(): void {
      }
    };
    Scrollbar::paintBar($target, new Rect(0, 0, 10, 3), 4, 4, 20, 'horizontal', '#0000ff');
    assertSame([
      [0.0, 2.6, 0.0, 3.0, 2.0, 3.0, '#0000ff'],
      [10.0, 2.6, 10.0, 3.0, 4.0, 3.0, '#0000ff'],
    ], $target->triangles, 'Horizontal scrollbar triangles should be cell-width tall and bottom-aligned.');
  },

  'scrollbar passes raw scroll state to pixel renderers' => function(): void {
    $target = new class implements RenderTarget {
      public array $scrollbars = [];

      public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
      }

      public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
      }

      public function drawScrollbar(Rect $rect, string $orientation, int $offset, int $visible, int $total, string|int|Color $color): void {
        $this->scrollbars[] = [$rect->x, $rect->y, $rect->width, $rect->height, $orientation, $offset, $visible, $total, Color::from($color)->hex()];
      }

      public function pushClip(Rect $rect): void {
      }

      public function popClip(): void {
      }
    };
    Scrollbar::paintBar($target, new Rect(8, 0, 1, 3), 2, 3, 7, 'vertical', '#0000ff');
    assertSame([[8, 0, 1, 3, 'vertical', 2, 3, 7, '#0000ff']], $target->scrollbars, 'Pixel renderers should receive row scroll state and compute continuous geometry themselves.');
  },

  'focus manager can honor requested popup focus' => function(): void {
    $root = new Dock('root');
    $context = new \SPTK\Core\ElementContext();
    $root->setContext($context);
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    $focus->rebuild($context->takeRequestedFocus());
    assertSame($popup, $focus->current(), 'Opened popup should be able to request focus.');
  },

  'popup opens nested submenu to the right of selected row' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening top-level submenu should add a popup.');
    $popup->handle(InputEvent::key('Right'));
    assertSame(3, count($root->children()), 'Right on a popup row with items should open a nested popup.');
    $child = $root->children()[2];
    assertInstanceOf(MenuPopup::class, $child, 'Nested submenu should be a menu popup.');
    assertSame($popup->frame()->right(), $child->frame()->x, 'Nested popup should start at the right edge of its parent.');
    assertSame($popup->frame()->y, $child->frame()->y, 'Nested popup should start on the selected popup row.');
  },

  'popup keeps parent menu path highlighted while child is open' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening top-level submenu should add a popup.');
    $popup->handle(InputEvent::key('Right'));
    $buffer = new GridBuffer(40, 8);
    $root->render($buffer);
    assertSame('#000000', $buffer->cell($popup->frame()->x + 2, $popup->frame()->y)?->bg->hex(), 'Parent popup row should stay highlighted while child submenu is open.');
  },

  'popup opens nested submenu with enter and space' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening top-level submenu should add a popup.');
    $popup->handle(InputEvent::key('Enter'));
    assertSame(3, count($root->children()), 'Enter should open a nested submenu.');
    $popup->closeChildPopup();
    $popup->handle(InputEvent::key('Space'));
    assertSame(3, count($root->children()), 'Space should open a nested submenu.');
  },

  'closing top popup removes nested submenu chain' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Opening top-level submenu should add a popup.');
    $popup->handle(InputEvent::key('Right'));
    assertSame(3, count($root->children()), 'Fixture should have a nested submenu before closing.');
    $menu->closePopup(true);
    assertSame(1, count($root->children()), 'Closing the top popup should remove nested submenu popups too.');
  },

  'menubar can add submenu items dynamically' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', ['File']);
    $menu->addItem('Edit');
    $menu->addSubItem([0], ['label' => 'Open']);
    $menu->addSubItem([0], [
      'label' => 'Profiles',
      'items' => [],
    ]);
    $menu->addSubItem([0, 1], ['label' => 'Local']);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'Dynamically added submenu should open from the top item.');
    $open = $popup->item(0);
    assertTrue(is_array($open), 'Dynamically added submenu item should be an array item.');
    assertSame('Open', $open['label'] ?? '', 'Dynamically added submenu item should be present.');
    $popup->handle(InputEvent::key('Down'));
    $popup->handle(InputEvent::key('Right'));
    assertSame(3, count($root->children()), 'Dynamically added recursive submenu should open.');
    $child = $root->children()[2];
    assertInstanceOf(MenuPopup::class, $child, 'Recursive dynamic submenu should be a popup.');
    $local = $child->item(0);
    assertTrue(is_array($local), 'Recursive dynamic submenu item should be an array item.');
    assertSame('Local', $local['label'] ?? '', 'Recursive dynamic submenu item should be present.');
  },

  'menubar can use recursive menu item objects' => function(): void {
    $root = new Dock('root');
    $activated = false;
    $profiles = new MenuItem('Profiles');
    $profiles->addItem(new MenuItem([
      'label' => 'Local',
      'action' => function(MenuPopup $popup, int $index, MenuItem $item) use (&$activated): void {
        $activated = $item->label() === 'Local';
      },
    ]));
    $file = new MenuItem('File');
    $file->addItem($profiles);
    $menu = new MenuBar('menu');
    $menu->addItem($file);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $popup, 'MenuItem submenu should open from the top item.');
    $popup->handle(InputEvent::key('Right'));
    $child = $root->children()[2];
    assertInstanceOf(MenuPopup::class, $child, 'MenuItem child submenu should open recursively.');
    $child->handle(InputEvent::key('Enter'));
    assertSame(true, $activated, 'MenuItem action should receive the selected MenuItem object.');
  },

  'popup can add visible items and submenus dynamically' => function(): void {
    $root = new Dock('root');
    $popup = new MenuPopup('popup', ['Profiles']);
    $root->add($popup);
    $root->setFrame(new Rect(0, 0, 40, 8));
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    $popup->addItem('Close');
    $popup->addSubItem([0], ['label' => 'Local']);
    assertSame(2, $popup->preferredHeight(), 'Adding a visible popup item should update preferred height.');
    assertSame('Close', $popup->item(1), 'Added visible popup item should be present.');
    $popup->handle(InputEvent::key('Right'));
    assertSame(2, count($root->children()), 'Dynamically added visible submenu should open.');
    $child = $root->children()[1];
    assertInstanceOf(MenuPopup::class, $child, 'Visible dynamic submenu should be a popup.');
    $local = $child->item(0);
    assertTrue(is_array($local), 'Visible dynamic submenu item should be an array item.');
    assertSame('Local', $local['label'] ?? '', 'Visible dynamic submenu item should be present.');
  },

  'menubar item stays highlighted while popup is open' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $buffer = new GridBuffer(30, 5);
    $root->render($buffer);
    assertSame('#00ffff', $buffer->cell(3, 0)?->bg->hex(), 'Top-level item should stay highlighted while popup is open.');
  },

  'menubar closes previous popup when activating item without submenu' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    assertSame(2, count($root->children()), 'Opening first item should add a popup.');
    $menu->activateNextTopItem();
    assertSame(1, count($root->children()), 'Activating item without submenu should close the previous popup.');
  },

  'menubar action item remains selectable while browsing' => function(): void {
    $root = new Dock('root');
    $activated = false;
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
        'action' => function() use (&$activated): void {
          $activated = true;
        },
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $menu->activateNextTopItem();
    assertSame(1, count($root->children()), 'Selecting an action-only top item should close the previous popup.');
    assertSame(false, $activated, 'Arrowing onto an action-only top item should not run its action.');
    $buffer = new GridBuffer(30, 5);
    $root->render($buffer);
    assertSame('#00ffff', $buffer->cell(13, 0)?->bg->hex(), 'Action-only top item should stay highlighted while browsing.');
    $focus->dispatch(InputEvent::key('Enter'));
    assertSame(true, $activated, 'Enter should activate the selected action-only top item.');
  },

  'menubar function key opens submenu item' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    assertSame(2, count($root->children()), 'F1 should activate the first top-level item and open its submenu.');
    assertInstanceOf(MenuPopup::class, $root->children()[1], 'F1 should add a popup for submenu items.');
  },

  'menubar switches open submenu while moving across top items' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
        'items' => [
          ['label' => 'Copy'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $firstPopup = $root->children()[1];
    $menu->activateNextTopItem();
    assertSame(2, count($root->children()), 'Switching submenu should keep only one popup open.');
    assertTrue($root->children()[1] !== $firstPopup, 'Right should replace the open popup with the next submenu.');
  },

  'menubar reopens submenu after crossing item without submenu' => function(): void {
    $root = new Dock('root');
    $activated = false;
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
      [
        'label' => 'Edit',
        'action' => function() use (&$activated): void {
          $activated = true;
        },
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    assertSame(2, count($root->children()), 'Opening first item should add a popup.');
    $menu->activateNextTopItem();
    assertSame(1, count($root->children()), 'Moving to item without submenu should close the popup.');
    assertSame(false, $activated, 'Moving across a direct-action item should not run its action.');
    $menu->activatePreviousTopItem();
    assertSame(2, count($root->children()), 'Moving back to submenu item should reopen its popup.');
    assertInstanceOf(MenuPopup::class, $root->children()[1], 'Reopened child should be a menu popup.');
  },

  'escape closes open menu popup' => function(): void {
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          ['label' => 'Open'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);
    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1];
    $focus->rebuild((new \SPTK\Core\ElementContext())->takeRequestedFocus());
    $popup->handle(InputEvent::key('Escape'));
    assertSame(1, count($root->children()), 'Escape should close the open popup.');
  },

  'escape on submenu closes only the submenu' => function(): void {
    $root = new Dock('root');
    $context = new \SPTK\Core\ElementContext();
    $root->setContext($context);
    $menu = new MenuBar('menu', [
      [
        'label' => 'Filters',
        'items' => [
          [
            'label' => 'Assignee',
            'items' => [
              ['label' => 'Alice'],
              ['label' => 'Bob'],
            ],
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $popup = $root->children()[1] ?? null;
    assertInstanceOf(MenuPopup::class, $popup, 'Opening the top-level menu should create a popup.');
    assertSame($popup, $focus->current(), 'Opening the top-level menu should focus the parent popup.');
    $focus->dispatch(InputEvent::key('Right'));
    $child = $root->children()[2] ?? null;
    assertInstanceOf(MenuPopup::class, $child, 'Opening the nested menu should create a child popup.');
    assertSame($child, $focus->current(), 'Opening the nested menu should focus the child popup.');

    $focus->dispatch(InputEvent::key('Escape'));

    assertSame(2, count($root->children()), 'Escape from a child submenu should keep the parent popup open.');
    assertSame($popup, $root->children()[1], 'Escape from a child submenu should preserve the parent popup.');
    assertSame($popup, $focus->current(), 'Escape from a child submenu should return focus to the parent popup.');
  },

  'right on submenu does not switch top menu' => function(): void {
    $root = new Dock('root');
    $context = new \SPTK\Core\ElementContext();
    $root->setContext($context);
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
      [
        'label' => 'Edit',
        'items' => [
          ['label' => 'Copy'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $focus->dispatch(InputEvent::key('Right'));
    $child = $root->children()[2] ?? null;
    assertInstanceOf(MenuPopup::class, $child, 'Opening the nested menu should create a child popup.');

    $focus->dispatch(InputEvent::key('Right'));

    assertSame(0, $menu->cursor(), 'Right on a child submenu without a deeper submenu should not change the top menu selection.');
    assertSame(3, count($root->children()), 'Right on a child submenu should keep the current submenu path open.');
    assertSame($child, $focus->current(), 'Right on a child submenu should keep focus on the child submenu.');
  },

  'tab from submenu switches top menus' => function(): void {
    $root = new Dock('root');
    $context = new \SPTK\Core\ElementContext();
    $root->setContext($context);
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
      [
        'label' => 'Edit',
        'items' => [
          ['label' => 'Copy'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $focus->dispatch(InputEvent::key('Right'));
    assertInstanceOf(MenuPopup::class, $root->children()[2] ?? null, 'Opening the nested menu should create a child popup.');

    $focus->dispatch(InputEvent::key('Tab'));

    assertSame(1, $menu->cursor(), 'Tab from a submenu should move to the next top menu.');
    assertSame(2, count($root->children()), 'Tab from a submenu should replace the open popup chain with the next top menu popup.');
    assertInstanceOf(MenuPopup::class, $focus->current(), 'Tab from a submenu should focus the newly opened top menu popup.');
  },

  'shift tab from submenu wraps to previous top menu' => function(): void {
    $root = new Dock('root');
    $context = new \SPTK\Core\ElementContext();
    $root->setContext($context);
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'items' => [
          [
            'label' => 'Profiles',
            'items' => [
              ['label' => 'Local'],
            ],
          ],
        ],
      ],
      [
        'label' => 'Edit',
        'items' => [
          ['label' => 'Copy'],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setFrame(new Rect(0, 0, 40, 8));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $focus->dispatch(InputEvent::key('Right'));
    assertInstanceOf(MenuPopup::class, $root->children()[2] ?? null, 'Opening the nested menu should create a child popup.');

    $focus->dispatch(InputEvent::key('Tab', ['shift' => true]));

    assertSame(1, $menu->cursor(), 'Shift+Tab from the first top menu submenu should wrap to the previous top menu.');
    assertSame(2, count($root->children()), 'Shift+Tab from a submenu should replace the open popup chain with the previous top menu popup.');
    assertInstanceOf(MenuPopup::class, $focus->current(), 'Shift+Tab from a submenu should focus the newly opened top menu popup.');
  },

  'selectable popup item updates marker and keeps menu open' => function(): void {
    $popup = new MenuPopup('popup', [
      [
        'label' => 'System schemas',
        'selectable' => true,
        'leftValues' => ['', 'X'],
        'action' => function(): void {},
      ],
    ], [
      'leftValues' => ['', 'X'],
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);
    $popup->handle(InputEvent::key('Space'));
    $buffer = new GridBuffer($popup->preferredWidth(), $popup->preferredHeight());
    $popup->render($buffer);
    assertSame('X', $buffer->cell(1, 0)?->glyph, 'Checkbox selectable action should update the left marker.');
    assertSame('#0000ff', $buffer->cell(1, 0)?->fg->hex(), 'Selectable marker should use adornment color.');
  },

  'radio popup group updates only selected marker and keeps menu open' => function(): void {
    $popup = new MenuPopup('popup', [
      [
        'label' => 'Default',
        'selectable' => 'style',
        'checked' => true,
        'leftValues' => ['', '*'],
        'action' => function(): void {},
      ],
      [
        'label' => 'Dark',
        'selectable' => 'style',
        'leftValues' => ['', '*'],
        'action' => function(): void {},
      ],
    ], [
      'leftValues' => ['', '*'],
    ]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), 4));
    new FocusManager($popup);
    $popup->handle(InputEvent::key('Down'));
    $popup->handle(InputEvent::key('Space'));

    assertSame(false, $popup->item(0)['checked'] ?? null, 'Radio group should clear the previous item.');
    assertSame(true, $popup->item(1)['checked'] ?? null, 'Radio group should check the selected item.');
  },

  'enter commits selectable menu item state before action' => function(): void {
    $item = new MenuItem([
      'label' => 'Preview',
      'selectable' => true,
      'action' => function(): void {},
    ]);
    $popup = new MenuPopup('popup', [$item]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);

    $popup->handle(InputEvent::key('Enter'));

    assertSame(true, $item->checked(), 'Enter should commit selectable item state.');
  },

  'selectable popup item can toggle without action' => function(): void {
    $item = new MenuItem([
      'label' => 'Visible rulers',
      'selectable' => true,
    ]);
    $popup = new MenuPopup('popup', [$item]);
    $popup->setFrame(new Rect(0, 0, $popup->preferredWidth(), $popup->preferredHeight()));
    new FocusManager($popup);

    $popup->handle(InputEvent::key('Space'));

    assertSame(true, $item->checked(), 'Selectable item should not require an action to change state.');
  },

  'selectable popup item keeps popup focus after focus rebuild' => function(): void {
    $context = new \SPTK\Core\ElementContext();
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'View',
        'items' => [
          [
            'label' => 'System schemas',
            'selectable' => true,
            'leftValues' => ['', 'X'],
            'action' => function(): void {},
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $popup = $focus->current();
    assertInstanceOf(MenuPopup::class, $popup, 'Popup should receive focus when opened.');
    $focus->dispatch(InputEvent::key('Space'));

    assertSame($popup, $focus->current(), 'Selectable popup action should keep focus on the popup.');
    assertSame(2, count($root->children()), 'Selectable popup action should keep the popup open.');
  },

  'popup action item can keep menu open on enter' => function(): void {
    $context = new \SPTK\Core\ElementContext();
    $activated = false;
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'Filters',
        'items' => [
          [
            'label' => 'Clear',
            'closeOnActivate' => false,
            'action' => function() use (&$activated): void {
              $activated = true;
            },
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $popup = $focus->current();
    assertInstanceOf(MenuPopup::class, $popup, 'Popup should receive focus when opened.');
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame(true, $activated, 'Enter should run the action.');
    assertSame($popup, $focus->current(), 'Non-closing action should keep focus on the popup.');
    assertSame(2, count($root->children()), 'Non-closing action should keep the popup open.');
  },

  'popup action item can close menu on space' => function(): void {
    $activated = false;
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'Filters',
        'items' => [
          [
            'label' => 'JQL',
            'closeOnActivate' => true,
            'action' => function() use (&$activated): void {
              $activated = true;
            },
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setContext(new \SPTK\Core\ElementContext());
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    assertInstanceOf(MenuPopup::class, $focus->current(), 'Popup should receive focus when opened.');
    $focus->dispatch(InputEvent::key('Space'));

    assertSame(true, $activated, 'Space should run the action.');
    assertSame(1, count($root->children()), 'Explicit closing action should close the popup even on Space.');
  },

  'enter on selectable popup item runs action and closes menu' => function(): void {
    $activated = false;
    $root = new Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'Slide',
        'items' => [
          [
            'label' => 'Intro',
            'selectable' => true,
            'action' => function() use (&$activated): void {
              $activated = true;
            },
          ],
        ],
      ],
    ]);
    $root->dock($menu, 'top');
    $root->setContext(new \SPTK\Core\ElementContext());
    $root->setFrame(new Rect(0, 0, 30, 5));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('F1'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame(true, $activated, 'Enter should run the selectable popup action.');
    assertSame(1, count($root->children()), 'Enter should close the popup after running the selectable action.');
  },
];
