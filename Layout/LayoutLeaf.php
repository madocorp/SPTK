<?php

namespace SPTK\Layout;

use SPTK\Core\Widget;
use SPTK\Events\{EventContext, EventDispatcher};
use SPTK\Rendering\{Grid, GridWriter};

/** Holds one widget's grid tile, padded pixel area, and event subscriptions. */
final class LayoutLeaf {

  private $grid;
  private ?Tile $area = null;
  private array $screenEvents = [];
  private EventDispatcher $eventDispatcher;

  /** Build a leaf and retain its XML event subscriptions. */
  public function __construct(public string $widget, private string $width, private string $height, private Widget $instance, private array $events = [], private bool $navigate = true) {
    $this->eventDispatcher = new EventDispatcher();
    $this->instance->on('change', $this->notifyChange(...));
    $this->instance->on('reorder', $this->notifyReorder(...));
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
    if (!$selected && !($this->instance instanceof \SPTK\Widgets\StatusBar\StatusBar)) {
      $grid->darken($this->grid);
    }
  }

  /** Paint pending widget cells or fall back to its full tile. */
  public function paintUpdate(Grid $grid): void {
    if (!$this->instance->paintUpdate(new GridWriter($grid, $this->grid))) {
      $this->paint($grid);
    }
  }

  /** Paint pixel content in the cell rectangle or across the full measured tile without padding. */
  public function paintPixels(\SPTK\Rendering\PixelRenderer $renderer, WindowGeometry $geometry, bool $selected): void {
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $area = $this->instance->pixelArea($geometry->pixelArea($this->grid), $this->area);
    $this->instance->paintPixels($renderer, $area, $selected);
  }

  /** Measure this widget's background including padding at interior and window edges. */
  public function measureArea(Tile $windowGrid, WindowGeometry $geometry): void {
    $this->area = $geometry->backgroundArea($this->grid, $windowGrid);
  }

  /** Draw this widget's background, dimmed when it is not selected. */
  public function drawBackground(\SPTK\Rendering\PixelRenderer $renderer, bool $selected = true): void {
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $color = $this->instance->background();
    $renderer->fill($this->area, $selected || $this->instance instanceof \SPTK\Widgets\StatusBar\StatusBar ? $color : $color->darkened());
  }

  /** Forward raw input events to this widget. */
  public function handleEvent(mixed $event): bool {
    return $this->instance->handleInput($event);
  }

  /** Dispatch raw input to this widget's XML event subscriptions. */
  public function dispatchInput(string $type, mixed $event): bool {
    return $this->eventDispatcher->dispatch($this->events, new EventContext($type, $this->instance, $event), true);
  }

  /** Deliver a lifecycle notification to widget and screen subscriptions. */
  public function dispatchNotification(string $type): void {
    $this->instance->emit($type);
    $this->dispatchXmlNotification($type);
  }

  /** Forward a widget-originated change event to XML subscriptions. */
  private function notifyChange(): void {
    $this->dispatchXmlNotification('change');
  }

  /** Forward a widget-originated order change to XML subscriptions. */
  private function notifyReorder(): void {
    $this->dispatchXmlNotification('reorder');
  }

  /** Dispatch XML notifications without recursively emitting into the widget. */
  private function dispatchXmlNotification(string $type): void {
    $context = new EventContext($type, $this->instance);
    $this->eventDispatcher->dispatch($this->events, $context, false);
    $this->eventDispatcher->dispatch($this->screenEvents, $context, false);
  }

  /** Set the screen-level event subscriptions inherited by this leaf. */
  public function setScreenEvents(array $events): void {
    $this->screenEvents = $events;
  }

  public function name() {
    return $this->widget;
  }

  /** Return this leaf's widget instance for event context. */
  public function instance(): Widget {
    return $this->instance;
  }

  /** Report whether arrow movement may select this leaf as a destination. */
  public function navigate(): bool {
    return $this->navigate;
  }

  /** Return the explicit or preferred width used by the parent layout. */
  public function width() {
    return $this->size($this->width, 'width');
  }

  /** Return the explicit or preferred height used by the parent layout. */
  public function height() {
    return $this->size($this->height, 'height');
  }

  /** Resolve an omitted XML size from the widget preference or use the flexible default. */
  private function size(string $size, string $axis): string {
    if ($size !== '') {
      return $size;
    }
    $preferred = $axis === 'width' ? $this->instance->preferredWidth() : $this->instance->preferredHeight();
    if ($preferred !== null && $preferred > 0) {
      return (string)$preferred;
    }
    return '1*';
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->widget}\n";
  }

}
