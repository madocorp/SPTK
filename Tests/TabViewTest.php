<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\ElementContext;
use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Widgets\Button;
use SPTK\Widgets\Flow;
use SPTK\Widgets\Input;
use SPTK\Widgets\Label;
use SPTK\Widgets\TabView;

class TabSurfaceTarget extends GridBuffer implements SurfaceRenderTarget {

  public array $pixelLines = [];

  public function __construct(
    int $width,
    int $height,
    protected int $cellWidth = 10,
    protected int $cellHeight = 20
  ) {
    parent::__construct($width, $height);
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight, $this->cellHeight));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function currentSurfacePixelRect(): Rect {
    return new Rect(0, 0, $this->width() * $this->cellWidth, $this->height() * $this->cellHeight);
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
  }

  public function popSurface(): void {
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $this->pixelLines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
  }

}

return [
  'tab view renders active and inactive tab colors' => function(): void {
    $tabs = new TabView('tabs');
    $tabs->addTab('General', new Label('general', 'name'));
    $tabs->addTab('Flags', new Label('flags', 'flags'));
    $tabs->setFrame(new Rect(0, 0, 30, 4));

    $buffer = new GridBuffer(30, 3);
    $tabs->render($buffer);

    assertSame(' F1 General   F2 Flags        ', $buffer->line(0), 'Tabs should render as a one-line menu-like strip.');
    assertSame('#ffffff', $buffer->cell(1, 0)?->fg->hex(), 'Active tab should use white text.');
    assertSame('#000000', $buffer->cell(1, 0)?->bg->hex(), 'Active tab should use black background.');
    assertSame('#aaaaaa', $buffer->cell(12, 0)?->fg->hex(), 'Gap between tabs should use the normal foreground.');
    assertSame('#0000aa', $buffer->cell(12, 0)?->bg->hex(), 'Gap between tabs should use the normal background.');
    assertSame('#cccccc', $buffer->cell(14, 0)?->fg->hex(), 'Inactive tab should use light gray text.');
    assertSame('#555555', $buffer->cell(14, 0)?->bg->hex(), 'Inactive tab should use dark gray background.');
    assertSame('                              ', $buffer->line(1), 'Tabs should leave an empty row before pane content.');
    assertSame('name', mb_substr($buffer->line(2), 0, 4), 'Active pane should render below the tab strip gap.');
  },

  'tab view switches active pane with function keys' => function(): void {
    $context = new ElementContext();
    $tabs = new TabView('tabs');
    $first = new Input('first', 'one');
    $second = new Input('second', 'two');
    $tabs->addTab('First', $first);
    $tabs->addTab('Second', $second);
    $tabs->setContext($context);
    $tabs->setFrame(new Rect(0, 0, 24, 4));

    $focus = new FocusManager($tabs);
    assertSame('first', $focus->current()?->name(), 'The active pane should provide the tab view focus proxy.');
    assertSame(true, $focus->dispatch(InputEvent::key('F2')), 'F2 should switch to the second tab.');
    assertSame(1, $tabs->activeIndex(), 'The second tab should become active.');
    assertSame('second', $focus->current()?->name(), 'Focus should move to the newly active pane.');
  },

  'tab view exposes all active pane inputs to tab order' => function(): void {
    $context = new ElementContext();
    $root = new Flow('root');
    $tabs = new TabView('tabs');
    $pane = new Flow('pane');
    $first = new Input('first', 'one');
    $second = new Input('second', 'two');
    $button = new Button('ok', 'OK');
    $pane->place($first);
    $pane->place($second);
    $tabs->addTab('Fields', $pane);
    $root->place($tabs, 6);
    $root->place($button);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 24, 8));

    $focus = new FocusManager($root);
    assertSame('first', $focus->current()?->name(), 'TabView should initially focus the first active pane input.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('second', $focus->current()?->name(), 'Tab should continue through active pane inputs.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('ok', $focus->current()?->name(), 'Tab should leave the tab view after the active pane inputs.');
  },

  'tab view draws a two pixel separator under the tab strip' => function(): void {
    $tabs = new TabView('tabs');
    $tabs->addTab('General', new Label('general', 'pane'));
    $tabs->setFrame(new Rect(1, 2, 20, 3));

    $target = new TabSurfaceTarget(30, 8, 10, 20);
    $tabs->render($target);

    assertSame([[10.0, 60.0, 210.0, 60.0, '#000000', 2]], $target->pixelLines, 'Tab separator should be a two-pixel line below the tab row.');
  },
];
