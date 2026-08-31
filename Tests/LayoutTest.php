<?php

namespace SPTK\Tests;

use SPTK\Core\Rect;
use SPTK\Core\Len;
use SPTK\Core\Place;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Widgets\Box;
use SPTK\Widgets\Dock;
use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Widgets\Checkbox;
use SPTK\Widgets\DialogBox;
use SPTK\Widgets\Flow;
use SPTK\Widgets\FlowRow;
use SPTK\Widgets\Input;
use SPTK\Widgets\Label;
use SPTK\Widgets\StatusBar;
use SPTK\Widgets\TextBlock;

class DockProbeTarget implements RenderTarget {

  public array $fills = [];
  public array $lines = [];
  public array $rects = [];
  public array $outerRects = [];

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fills[] = [$rect->x, $rect->y, $rect->width, $rect->height, Color::from($bg ?? '#000000')->hex()];
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fill($rect, $value, $fg, $bg, $flags);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
    $this->lines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex()];
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    $this->rects[] = [$rect->x, $rect->y, $rect->width, $rect->height, Color::from($color)->hex(), $thickness];
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    $this->outerRects[] = [$rect->x, $rect->y, $rect->width, $rect->height, Color::from($color)->hex(), $thickness];
  }

  public function pushClip(Rect $rect): void {
  }

  public function popClip(): void {
  }

}

class SurfaceProbeTarget extends DockProbeTarget implements SurfaceRenderTarget {

  public array $surfaces = [];
  public array $pixelFills = [];
  public array $pixelLines = [];
  protected array $stack = [];

  public function __construct(
    protected Rect $surface = new Rect(0, 0, 800, 530),
    protected int $cellWidth = 10,
    protected int $cellHeight = 23
  ) {
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight + 2, $this->cellHeight));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function currentSurfacePixelRect(): Rect {
    return $this->surface;
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
    $this->surfaces[] = [$pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, $columns, $rows, $background === null ? null : Color::from($background)->hex(), $gridAlignment];
    $this->stack[] = $this->surface;
    $this->surface = $pixelRect;
  }

  public function popSurface(): void {
    $this->surface = array_pop($this->stack);
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
    $this->pixelFills[] = [$pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, Color::from($color)->hex()];
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $this->pixelLines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
  }

}

return [
  'flow adds default gaps between widget groups' => function(): void {
    $flow = new Flow('flow');
    $input = new Input('input', 'value');
    $text = new TextBlock('text', 'body');
    $flow->place($input)->place($text);
    $flow->setFrame(new Rect(0, 0, 20, $flow->preferredRowsForColumns(20)));

    assertSame(3, $flow->preferredRowsForColumns(20), 'Flow should include one default row between standalone widgets.');
    assertSame(0, $input->frame()->y, 'First widget should start on the first flow row.');
    assertSame(2, $text->frame()->y, 'Second standalone widget should be placed after one empty row.');
  },

  'flow keeps labels attached to the following widget' => function(): void {
    $flow = new Flow('flow');
    $first = new Input('first', 'one');
    $label = new Label('label', 'Name:');
    $second = new Input('second', 'two');
    $flow->place($first)->place($label)->place($second);
    $flow->setFrame(new Rect(0, 0, 20, $flow->preferredRowsForColumns(20)));

    assertSame(4, $flow->preferredRowsForColumns(20), 'Flow should add a gap before a label group but not after the label.');
    assertSame(0, $first->frame()->y, 'First widget should start on the first flow row.');
    assertSame(2, $label->frame()->y, 'Label should start after the previous widget group gap.');
    assertSame(3, $second->frame()->y, 'Widget after a label should attach directly below the label.');
  },

  'flow keeps consecutive checkboxes together' => function(): void {
    $flow = new Flow('flow');
    $first = new Checkbox('first', 'First');
    $second = new Checkbox('second', 'Second');
    $input = new Input('input', 'value');
    $flow->place($first)->place($second)->place($input);
    $flow->setFrame(new Rect(0, 0, 20, $flow->preferredRowsForColumns(20)));

    assertSame(4, $flow->preferredRowsForColumns(20), 'Consecutive checkboxes should not add a gap, but the next widget group should.');
    assertSame(0, $first->frame()->y, 'First checkbox should start on the first flow row.');
    assertSame(1, $second->frame()->y, 'Second checkbox should attach directly after the first checkbox.');
    assertSame(3, $input->frame()->y, 'Widget after a checkbox group should start after one empty row.');
  },

  'flow explicit child rows still use default gaps' => function(): void {
    $flow = new Flow('flow');
    $first = new TextBlock('first', 'first');
    $second = new Input('second', 'second');
    $flow->place($first, 2)->place($second, 1);
    $flow->setFrame(new Rect(0, 0, 20, $flow->preferredRowsForColumns(20)));

    assertSame(4, $flow->preferredRowsForColumns(20), 'Explicit child rows should not disable automatic flow gaps.');
    assertSame(0, $first->frame()->y, 'Explicit-height widget should start on the first flow row.');
    assertSame(2, $first->frame()->height, 'Explicit child row count should still control child height.');
    assertSame(3, $second->frame()->y, 'Next widget should start after the explicit rows and default gap.');
  },

  'flow row uses tallest multiline child as preferred height' => function(): void {
    $row = new FlowRow('row');
    $text = new TextBlock('text', 'alpha beta gamma');
    $input = new Input('input', 'value');
    $row->place($text, 6)->place($input, 8);

    assertSame(3, $row->preferredRows(), 'FlowRow should reserve the tallest child height.');
    $row->setFrame(new Rect(0, 0, 20, $row->preferredRows()));

    assertSame(3, $text->frame()->height, 'Multiline children should receive their preferred row height.');
    assertSame(1, $input->frame()->height, 'Single-line children should keep their own height.');
  },

  'flow reserves multiline flow row height' => function(): void {
    $flow = new Flow('flow');
    $row = new FlowRow('row');
    $row->place(new TextBlock('text', 'alpha beta gamma'), 6)
      ->place(new Input('input', 'value'), 8);
    $after = new Input('after', 'after');
    $flow->place($row)->place($after);
    $flow->setFrame(new Rect(0, 0, 20, $flow->preferredRowsForColumns(20)));

    assertSame(5, $flow->preferredRowsForColumns(20), 'Flow should include multiline FlowRow height and the default group gap.');
    assertSame(0, $row->frame()->y, 'FlowRow should start on the first flow row.');
    assertSame(3, $row->frame()->height, 'Flow should assign the multiline FlowRow preferred height.');
    assertSame(4, $after->frame()->y, 'The next widget should be placed after the whole FlowRow.');
  },

  'dock reserves top and bottom rows' => function(): void {
    $root = new Dock('root');
    $top = new StatusBar('top', 'top');
    $body = new Label('body', 'body');
    $bottom = new StatusBar('bottom', 'bottom');
    $root->place($top, Place::dock('top'))
      ->place($bottom, Place::dock('bottom'))
      ->place($body, Place::fill());
    $root->setFrame(new Rect(0, 0, 20, 5));
    assertSame(0, $top->frame()->y, 'Top dock should start at first row.');
    assertSame(4, $bottom->frame()->y, 'Bottom dock should use last row.');
    assertSame(1, $body->frame()->y, 'Fill child should start after top dock.');
    assertSame(3, $body->frame()->height, 'Fill child should receive remaining height.');
  },

  'dock divides horizontal space with separator' => function(): void {
    $root = new Dock('split');
    $left = new Label('left', 'left');
    $right = new Label('right', 'right');
    $root->place($left, Place::dock('left', Len::percent(25), Len::cell(1)))
      ->place($right, Place::fill());
    $root->setFrame(new Rect(0, 0, 40, 5));
    assertSame(10, $left->frame()->width, 'Left pane should receive ratio width.');
    assertSame(11, $right->frame()->x, 'Right pane should start after separator column.');
    assertSame(29, $right->frame()->width, 'Right pane should receive remaining width.');
  },

  'dock divides vertical space with separator row' => function(): void {
    $root = new Dock('split');
    $top = new Label('top', 'top');
    $bottom = new Label('bottom', 'bottom');
    $root->place($top, Place::dock('top', Len::percent(50), Len::cell(1)))
      ->place($bottom, Place::fill());
    $root->setFrame(new Rect(0, 0, 20, 9));
    assertSame(4, $top->frame()->height, 'Top pane should receive ratio height after reserving separator row.');
    assertSame(5, $bottom->frame()->y, 'Bottom pane should start after separator row.');
    assertSame(4, $bottom->frame()->height, 'Bottom pane should receive remaining height.');
  },

  'dock renders main separator in muted gray' => function(): void {
    $root = new Dock('split');
    $root->place(new Label('left', 'left'), Place::dock('left', Len::percent(25), Len::cell(1)))
      ->place(new Label('right', 'right'), Place::fill());
    $root->setFrame(new Rect(0, 0, 40, 5));
    $target = new DockProbeTarget();
    $root->render($target);
    assertSame([10, 0, 1, 5, '#777777'], $target->fills[0], 'Main separator should fill its full cell area in muted gray.');
    assertSame([], $target->lines, 'Dock separator should not draw a separate center line.');
  },

  'nested dock separators attach at element edges' => function(): void {
    $root = new Dock('root');
    $left = new Dock('left');
    $left->place(new Label('top', 'top'), Place::dock('top', Len::cell(2), Len::cell(1)))
      ->place(new Label('bottom', 'bottom'), Place::fill());
    $root->place($left, Place::dock('left', Len::cell(3), Len::cell(1)))
      ->place(new Label('right', 'right'), Place::fill());
    $root->setFrame(new Rect(0, 0, 8, 5));
    $target = new DockProbeTarget();
    $root->render($target);
    assertSame([0, 2, 3, 1, '#777777'], $target->fills[0], 'Child separator should fill to the child element edge.');
    assertSame([3, 0, 1, 5, '#777777'], $target->fills[1], 'Parent separator should fill the adjacent edge from top to bottom.');
  },

  'dock renders dialog separator with panel background' => function(): void {
    $dialog = new DialogBox('dialog');
    $split = new Dock('split');
    $split->place(new Label('top', 'top'), Place::dock('top', Len::percent(50), Len::cell(1)))
      ->place(new Label('bottom', 'bottom'), Place::fill());
    $dialog->add($split);
    $dialog->setFrame(new Rect(0, 0, 20, 9));
    $target = new DockProbeTarget();
    $dialog->render($target);
    assertSame([0, 4, 20, 1, '#0000aa'], $target->fills[1], 'Dialog separator should fill its full cell area with the dialog child background.');
    assertSame([], $target->lines, 'Dialog dock separator should not draw a separate center line.');
  },

  'dock renders child grid surfaces from pixel bounds' => function(): void {
    $root = new Dock('root');
    $body = new Label('body', 'body');
    $root->place(new StatusBar('top', 'top'), Place::dock('top'))
      ->place(new StatusBar('bottom', 'bottom'), Place::dock('bottom'))
      ->place($body, Place::fill());
    $root->setFrame(new Rect(0, 0, 80, 23));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 800, 530));
    $root->render($target);
    assertSame([0, 0, 800, 23, 80, 1, '#0000aa', 'top-left'], $target->surfaces[0], 'Top dock should receive one row of pixels.');
    assertSame([0, 507, 800, 23, 80, 1, '#0000aa', 'top-left'], $target->surfaces[1], 'Bottom dock should receive the final row of pixels.');
    assertSame([0, 23, 800, 484, 80, 21, '#0000aa', 'center'], $target->surfaces[2], 'Fill dock should receive the pixel remainder as its own grid.');
  },

  'nested dock renders cell based child surfaces on whole grid cells' => function(): void {
    $container = new Dock('container');
    $split = new Dock('split');
    $split->place(new Label('left', 'left'), Place::dock('left', Len::percent(67), Len::cell(1)))
      ->place(new Label('right', 'right'), Place::fill());
    $container->place($split, Place::fill());
    $container->setFrame(new Rect(0, 0, 80, 5));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 805, 100), 10, 20);
    $split->render($target);
    assertSame([0, 0, 530, 100, 53, 5, '#0000aa', 'center'], $target->surfaces[0], 'Docked pane should render at its cell layout size.');
    assertSame([540, 0, 260, 100, 26, 5, '#0000aa', 'center'], $target->surfaces[1], 'Fill pane should render at its cell layout size.');
    assertSame(10, count($target->pixelFills), 'Cell separator should cover exactly one cell column.');
    assertSame([530, 0, 1, 100, '#777777'], $target->pixelFills[0], 'Cell separator should start after the left pane.');
    assertSame([539, 0, 1, 100, '#777777'], $target->pixelFills[9], 'Cell separator should end before the fill pane.');
  },

  'dock renders child grid surfaces and pixel separator' => function(): void {
    $root = new Dock('split');
    $root->place(new Label('left', 'left'), Place::dock('left', Len::percent(50), Len::px(2)))
      ->place(new Label('right', 'right'), Place::fill());
    $root->setFrame(new Rect(0, 0, 80, 5));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 805, 120));
    $root->render($target);
    assertSame([0, 0, 402, 120, 40, 5, '#0000aa', 'center'], $target->surfaces[0], 'Docked pane should own a pixel-bounded local grid.');
    assertSame([404, 0, 401, 120, 40, 5, '#0000aa', 'center'], $target->surfaces[1], 'Fill pane should own the remaining pixels.');
    assertSame([402, 0, 1, 120, '#777777'], $target->pixelFills[0], 'Separator should fill the first pixel line.');
    assertSame([403, 0, 1, 120, '#777777'], $target->pixelFills[1], 'Separator should fill the second pixel line.');
    assertSame([], $target->pixelLines, 'Pixel separator should not draw a separate center line.');
  },

  'nested dock surface rendering respects its own frame' => function(): void {
    $dock = new Dock('nested-split');
    $dock->place(new Label('left', 'left'), Place::dock('left', Len::percent(50), Len::px(2)))
      ->place(new Label('right', 'right'), Place::fill());
    $dock->setFrame(new Rect(2, 1, 40, 5));
    $target = new SurfaceProbeTarget(new Rect(10, 20, 800, 230));
    $dock->render($target);
    assertSame([30, 43, 200, 115, 20, 5, '#0000aa', 'center'], $target->surfaces[0], 'Nested docked pane should start at the element frame offset.');
    assertSame([232, 43, 198, 115, 19, 5, '#0000aa', 'center'], $target->surfaces[1], 'Nested fill pane should stay inside the element frame.');
    assertSame([230, 43, 1, 115, '#777777'], $target->pixelFills[0], 'Nested separator should be clipped to the element frame height.');
    assertSame([231, 43, 1, 115, '#777777'], $target->pixelFills[1], 'Nested separator should use the requested pixel thickness.');
  },

  'dock places child at absolute pixel position' => function(): void {
    $root = new Dock('root');
    $root->place(new Label('image', 'image'), Place::at(Len::px(13), Len::px(17), Len::px(123), Len::px(77)));
    $root->setFrame(new Rect(0, 0, 80, 20));
    $target = new SurfaceProbeTarget(new Rect(10, 20, 800, 400), 10, 20);
    $root->render($target);
    assertSame([23, 37, 123, 77, 12, 3, '#0000aa', 'center'], $target->surfaces[0], 'Pixel placement should offset from the current pixel surface and preserve requested pixel size.');
  },

  'place api docks from remaining space without overlap' => function(): void {
    $root = new Dock('root');
    $top = new Label('top', 'top');
    $left = new Label('left', 'left');
    $body = new Label('body', 'body');
    $root->place($top, Place::dock('top', Len::cell(2)))
      ->place($left, Place::dock('left', Len::cell(5)))
      ->place($body, Place::fill());
    $root->setFrame(new Rect(0, 0, 20, 10));

    assertSame(0, $top->frame()->y, 'Top dock should start at the parent top.');
    assertSame(2, $left->frame()->y, 'Left dock should start below the consumed top dock.');
    assertSame(8, $left->frame()->height, 'Left dock should occupy the remaining height.');
    assertSame(5, $body->frame()->x, 'Fill should start after the left dock.');
    assertSame(2, $body->frame()->y, 'Fill should stay below the top dock.');
  },

  'place api arranges repeated edge docks edge to center' => function(): void {
    $root = new Dock('root');
    $first = new Label('first', 'first');
    $second = new Label('second', 'second');
    $body = new Label('body', 'body');
    $root->place($first, Place::dock('left', Len::cell(3)))
      ->place($second, Place::dock('left', Len::cell(4)))
      ->place($body, Place::fill());
    $root->setFrame(new Rect(0, 0, 20, 5));

    assertSame(0, $first->frame()->x, 'First left dock should sit on the outside edge.');
    assertSame(3, $second->frame()->x, 'Second left dock should be placed inside the first.');
    assertSame(7, $body->frame()->x, 'Fill should start after both left docks.');
  },

  'place api requires fill to be the last consuming placement' => function(): void {
    $root = new Dock('root');
    $root->place(new Label('body', 'body'), Place::fill());
    try {
      $root->place(new Label('top', 'top'), Place::dock('top', Len::cell(1)));
    } catch (\InvalidArgumentException) {
      assertTrue(true, 'Dock after fill should throw.');
      return;
    }
    assertTrue(false, 'Dock after fill should throw.');
  },

  'place api supports percent and negative free placement' => function(): void {
    $root = new Dock('root');
    $child = new Label('child', 'child');
    $root->place($child, Place::at(Len::percent(50), Len::px(-2), Len::percent(25), Len::cell(2)));
    $root->setFrame(new Rect(10, 20, 40, 10));

    assertSame(30, $child->frame()->x, 'Percent x should resolve against parent width.');
    assertSame(26, $child->frame()->y, 'Negative y should align from parent bottom with an inset.');
    assertSame(10, $child->frame()->width, 'Percent width should resolve against parent width.');
    assertSame(2, $child->frame()->height, 'Cell height should resolve as rows in grid layout.');
  },

  'place cursor stacks children automatically' => function(): void {
    $root = new Dock('root');
    $first = new Label('first', 'first');
    $second = new TextBlock('second', 'alpha beta gamma delta');
    $body = new Label('body', 'body');
    $root->place($first, Place::cursor(Len::cell(1)))
      ->place($second, Place::cursor(Len::content()))
      ->place($body, Place::fill());
    $root->setFrame(new Rect(0, 0, 10, 8));

    assertSame(0, $first->frame()->y, 'First cursor child should start at the cursor origin.');
    assertSame(1, $second->frame()->y, 'Second cursor child should start after the first child.');
    assertSame(3, $second->frame()->height, 'Content height should use preferred rows for the assigned width.');
    assertSame(4, $body->frame()->y, 'Fill should start after cursor-placed children.');
  },

  'flow accepts typed cursor placement' => function(): void {
    $flow = new Flow('flow');
    $child = new TextBlock('text', 'alpha beta gamma delta');
    $flow->place($child, Place::cursor(Len::cell(2)));
    $flow->setFrame(new Rect(0, 0, 10, 5));

    assertSame(2, $child->frame()->height, 'Flow should accept Place::cursor height.');
  },

  'flow cursor content height stays dynamic' => function(): void {
    $flow = new Flow('flow');
    $child = new TextBlock('text', 'alpha beta gamma delta');
    $flow->place($child, Place::cursor(Len::content()));
    $flow->setFrame(new Rect(0, 0, 10, 5));

    assertSame(3, $child->frame()->height, 'Flow should resolve cursor content height from child preferred rows.');
  },

  'flow cursor percent height resolves against available rows' => function(): void {
    $flow = new Flow('flow');
    $top = new Label('top', 'top');
    $fill = new TextBlock('fill', 'fill');
    $flow->place($top, Place::cursor(Len::cell(2)));
    $flow->place($fill, Place::cursor(Len::percent(100)));
    $flow->setFrame(new Rect(0, 0, 20, 8));

    assertSame(2, $top->frame()->height, 'Fixed cursor child should keep its requested height.');
    assertSame(2, $fill->frame()->y, 'Percent cursor child should start after the label without an extra gap.');
    assertSame(6, $fill->frame()->height, 'Percent cursor child should fill the remaining flow height.');
  },

  'flow row accepts typed cursor placement width' => function(): void {
    $row = new FlowRow('row');
    $child = new Label('child', 'child');
    $row->place($child, Place::cursor(null, Len::cell(6)));
    $row->setFrame(new Rect(0, 0, 20, 1));

    assertSame(6, $child->frame()->width, 'FlowRow should accept Place::cursor width.');
  },

  'box draws default background and inside border' => function(): void {
    $box = new Box('box', new Label('child', 'child'), ['border' => 'inside', 'borderColor' => '#ffffff']);
    $box->setFrame(new Rect(1, 2, 8, 4));
    $target = new DockProbeTarget();
    $box->render($target);

    assertSame([1, 2, 8, 4, '#0000aa'], $target->fills[0], 'Box should fill with theme background by default.');
    assertSame([1, 2, 8, 4, '#ffffff', 1], $target->rects[0], 'Inside border should draw on the assigned box.');
    assertSame(2, $box->children()[0]->frame()->x, 'Inside border should inset child content.');
  },

  'box can skip pixel surface background when transparent' => function(): void {
    $root = new Dock('root');
    $root->place(new Box('transparent', new Label('child', 'child'), ['background' => 'transparent']), Place::at(Len::px(5), Len::px(7), Len::px(50), Len::px(30)));
    $root->setFrame(new Rect(0, 0, 10, 5));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 100, 80), 10, 20);
    $root->render($target);

    assertSame([5, 7, 50, 30, 5, 1, null, 'center'], $target->surfaces[0], 'Transparent Box should not request a surface background fill.');
  },

  'box draws outside border around assigned frame' => function(): void {
    $box = new Box('box', null, ['border' => 'outside', 'borderColor' => '#ffffff']);
    $box->setFrame(new Rect(1, 2, 8, 4));
    $target = new DockProbeTarget();
    $box->render($target);

    assertSame([1, 2, 8, 4, '#ffffff', 1], $target->outerRects[0], 'Outside border should draw around the assigned box.');
  },
];
