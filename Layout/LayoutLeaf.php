<?php

namespace SPTK\Layout;

use SPTK\Core\{ChangeAwareWidget, EventContext, EventDispatcher, InputHandler, Widget};
use SPTK\Rendering\{Grid, GridWriter};

final class LayoutLeaf {

  private $grid;
  private ?Tile $area = null;
  private array $screenEvents = [];
  private EventDispatcher $eventDispatcher;

  /** Build a leaf and connect change-aware widgets to its XML handlers. */
  public function __construct(public string $widget, private string $width, private string $height, private Widget $instance, private array $events = []) {
    $this->eventDispatcher = new EventDispatcher();
    if ($this->instance instanceof ChangeAwareWidget) {
      $this->instance->setChangeListener([$this, 'notifyChange']);
    }
  }

  public function setGrid($grid) {
    $this->grid = $grid;
  }

  public function grid(): Tile {
    return $this->grid;
  }

  /** Paint this widget and dim its cell colors when it is not selected. */
  public function paint(Grid $grid, bool $selected = true): void {
    $this->instance->paint(new GridWriter($grid, $this->grid));
    if (!$selected) {
      $grid->darken($this->grid);
    }
  }

  public function measureArea(Tile $windowGrid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $left = 0;
    if ($this->grid->x !== 0) {
      $left = $this->grid->x * $cellWidth + $offsetX - $cellWidth;
    }
    $top = 0;
    if ($this->grid->y !== 0) {
      $top = $this->grid->y * $cellHeight + $offsetY - intdiv($cellHeight, 2);
    }
    $right = $windowWidth;
    if ($this->grid->x + $this->grid->width < $windowGrid->width) {
      $right = ($this->grid->x + $this->grid->width) * $cellWidth + $offsetX + $cellWidth;
    }
    $bottom = $windowHeight;
    if ($this->grid->y + $this->grid->height < $windowGrid->height) {
      $bottom = ($this->grid->y + $this->grid->height) * $cellHeight + $offsetY + intdiv($cellHeight + 1, 2);
    }
    $this->area = new Tile($left, $top, $right - $left, $bottom - $top);
  }

  /** Draw this widget's background, dimmed when it is not selected. */
  public function drawBackground(\SPTK\Rendering\PixelRenderer $renderer, bool $selected = true): void {
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $color = $this->instance->background();
    $renderer->fill($this->area, $selected ? $color : $color->darkened());
  }

  /** Forward input events to widgets that implement the optional input contract. */
  public function handleEvent(mixed $event): bool {
    return $this->instance instanceof InputHandler && $this->instance->handleInput($event);
  }

  /** Dispatch raw input to this widget's XML event subscriptions. */
  public function dispatchInput(string $type, mixed $event): bool {
    return $this->eventDispatcher->dispatch($this->events, new EventContext($type, $this->instance, $event), true);
  }

  /** Deliver a lifecycle notification to widget and screen subscriptions. */
  public function dispatchNotification(string $type): void {
    $this->instance->emit($type);
    $context = new EventContext($type, $this->instance);
    $this->eventDispatcher->dispatch($this->events, $context, false);
    $this->eventDispatcher->dispatch($this->screenEvents, $context, false);
  }

  /** Set the screen-level event subscriptions inherited by this leaf. */
  public function setScreenEvents(array $events): void {
    $this->screenEvents = $events;
  }

  /** Notify the leaf that its widget value changed. */
  public function notifyChange(): void {
    $this->dispatchNotification('change');
  }

  public function name() {
    return $this->widget;
  }

  /** Return this leaf's widget instance for event context. */
  public function instance(): Widget {
    return $this->instance;
  }

  public function width() {
    return $this->width;
  }

  public function height() {
    return $this->height;
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->widget}\n";
  }

}
