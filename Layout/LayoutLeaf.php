<?php

namespace SPTK\Layout;

use SPTK\Core\Color;
use SPTK\Core\Widget;
use SPTK\Events\{EventContext, EventDispatcher};
use SPTK\Rendering\{Grid, GridWriter};

/** Holds one widget's grid tile, padded pixel area, and event subscriptions. */
final class LayoutLeaf {

  private $grid;
  private ?Tile $area = null;
  private ?Tile $pixelTile = null;
  private ?Tile $pixelBorder = null;
  private ?Tile $pixelBackgroundArea = null;
  private ?Tile $pixelContent = null;
  private ?Tile $navigationPixelTile = null;
  private ?Tile $viewport = null;
  private ?Color $pixelParentBackground = null;
  private ?Color $pixelBackground = null;
  private array $screenEvents = [];
  private EventDispatcher $eventDispatcher;

  /** Build a leaf with optional grid padding paint and its event subscriptions. */
  public function __construct(public string $widget, private string $width, private string $height, private Widget $instance, private array $events = [], private bool $navigate = true, private ?PixelBox $box = null, private bool $paintBackground = true) {
    $this->eventDispatcher = new EventDispatcher();
    $this->instance->on('change', $this->notifyChange(...));
    $this->instance->on('reorder', $this->notifyReorder(...));
  }

  public function setGrid($grid) {
    $this->grid = $grid;
    $this->pixelTile = null;
    $this->navigationPixelTile = null;
  }

  public function grid(): Tile {
    return $this->grid;
  }

  /** Limit an overflowing grid widget to its ancestor's visible viewport. */
  public function setViewport(?Tile $viewport): void {
    $this->viewport = $viewport;
  }

  /** Return the grid cells that may be drawn inside an overflow viewport. */
  public function visibleGrid(): Tile {
    return $this->viewport === null ? $this->grid : $this->grid->intersect($this->viewport);
  }

  /** Use exact pixel geometry for navigation when this leaf belongs to a pixel layout. */
  public function navigationArea(): Tile {
    return $this->navigationPixelTile ?? $this->pixelTile ?? $this->grid;
  }

  /** Give a grouped layout's synthetic focus leaf the group's measured pixel rectangle. */
  public function setNavigationPixelTile(Tile $tile): void {
    $this->navigationPixelTile = $tile;
  }

  public function pixelTile(): ?Tile {
    return $this->pixelTile;
  }

  public function isPixel(): bool {
    return $this->pixelTile !== null;
  }

  public function pixelContent(): ?Tile {
    return $this->pixelContent;
  }

  /** Measure the layout-owned box and give the widget only its inner rectangle. */
  public function measurePixel(Tile $tile, int $viewportWidth, int $viewportHeight, Color $parentBackground): void {
    if (!$this->instance->paintsPixels() && !($this->instance instanceof \SPTK\Widgets\Empty\Placeholder)) {
      throw new \LogicException('Pixel layouts require a pixel-painting widget or an Empty placeholder.');
    }
    $this->pixelTile = $tile;
    $this->pixelParentBackground = $parentBackground;
    $this->pixelBackground = $this->box?->background ?? $this->instance->background();
    [$this->pixelBorder, $this->pixelBackgroundArea, $this->pixelContent] = ($this->box ?? new PixelBox())->areas($tile, $viewportWidth, $viewportHeight);
  }

  /** Paint this widget and dim its cell colors when it is not selected. */
  public function paint(Grid $grid, bool $selected = true): void {
    if ($this->pixelTile !== null) {
      return;
    }
    $visible = $this->visibleGrid();
    if ($visible->width === 0 || $visible->height === 0) {
      return;
    }
    $this->instance->paint(new GridWriter($grid, $this->grid, $this->viewport));
    if (!$selected && $this->instance->dimContentWhenUnselected()) {
      $grid->darken($this->visibleGrid());
    }
  }

  /** Paint pending widget cells or fall back to its full tile. */
  public function paintUpdate(Grid $grid): void {
    if ($this->pixelTile !== null) {
      return;
    }
    $visible = $this->visibleGrid();
    if ($visible->width === 0 || $visible->height === 0) {
      return;
    }
    if (!$this->instance->paintUpdate(new GridWriter($grid, $this->grid, $this->viewport))) {
      $this->paint($grid);
    }
  }

  /** Paint pixel content in the cell rectangle or across the full measured tile without padding. */
  public function paintPixels(\SPTK\Rendering\PixelRenderer $renderer, WindowGeometry $geometry, bool $selected): void {
    if ($this->viewport !== null && $this->instance->paintsPixels() && !$this->instance->clipsPixelViewport()) {
      throw new \LogicException('Overflow viewports currently require grid-only widgets.');
    }
    if ($this->pixelContent !== null) {
      $this->instance->paintPixels($renderer, $this->pixelContent, $selected);
      return;
    }
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $area = $this->instance->pixelArea($geometry->pixelArea($this->grid), $this->area);
    $clip = $this->viewport === null ? $area : $geometry->pixelArea($this->visibleGrid())->intersect($area);
    $this->instance->paintClippedPixels($renderer, $area, $clip, $selected);
  }

  /** Measure this widget's background including padding at interior and window edges. */
  public function measureArea(Tile $windowGrid, WindowGeometry $geometry): void {
    if ($this->pixelTile !== null) {
      return;
    }
    if ($this->grid->width === 0 || $this->grid->height === 0) {
      $this->area = new Tile(0, 0, 0, 0);
      return;
    }
    $this->area = $geometry->backgroundArea($this->grid, $windowGrid);
    if ($this->viewport !== null) {
      $this->area = $this->area->intersect($geometry->backgroundArea($this->viewport, $windowGrid));
    }
  }

  /** Draw this widget's background, dimmed when it is not selected. */
  public function drawBackground(\SPTK\Rendering\PixelRenderer $renderer, bool $selected = true): void {
    if ($this->pixelTile !== null) {
      $background = $selected ? $this->pixelBackground : $this->pixelBackground->darkened();
      $parentBackground = $selected ? $this->pixelParentBackground : $this->pixelParentBackground->darkened();
      $borderColor = $this->box?->borderColor ?? new Color(71, 85, 104);
      $renderer->fill($this->pixelTile, $parentBackground);
      $renderer->fill($this->pixelBorder, $selected ? $borderColor : $borderColor->darkened());
      $renderer->fill($this->pixelBackgroundArea, $background);
      return;
    }
    if (!$this->paintBackground) {
      return;
    }
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $color = $this->instance->background();
    $keepColor = $selected || !$this->instance->dimBackgroundWhenUnselected();
    $renderer->fill($this->area, $keepColor ? $color : $color->darkened());
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

  /** Measure text at its assigned width or use a nontext widget's preferred height. */
  public function naturalHeight(int $width): int {
    if (!$this->instance instanceof \SPTK\Widgets\StyledText\StyledText) {
      return max(1, $this->instance->preferredHeight() ?? 1);
    }
    $font = \SPTK\App::fontOrNull();
    $cellWidth = $font?->cellWidth() ?? 8;
    $cellHeight = $font?->cellHeight() ?? 16;
    return max(1, (int)ceil($this->instance->contentHeight(max(1, $width * $cellWidth)) / $cellHeight));
  }

  /** Measure text and layout-owned edges directly in pixels. */
  public function naturalPixelHeight(int $width, int $viewportWidth, int $viewportHeight): int {
    $box = $this->box ?? new PixelBox();
    $margin = PixelBox::edges($box->margin, $viewportWidth, $viewportHeight);
    $border = PixelBox::edges($box->borderWidth, $viewportWidth, $viewportHeight);
    $padding = PixelBox::edges($box->padding, $viewportWidth, $viewportHeight);
    $edges = $margin['top'] + $margin['bottom'] + $border['top'] + $border['bottom'] + $padding['top'] + $padding['bottom'];
    $innerWidth = max(1, $width - $margin['left'] - $margin['right'] - $border['left'] - $border['right'] - $padding['left'] - $padding['right']);
    $content = $this->instance instanceof \SPTK\Widgets\StyledText\StyledText
      ? $this->instance->contentHeight($innerWidth)
      : ($this->instance->preferredPixelHeight($innerWidth) ?? 0);
    return $edges + $content;
  }

  /** Resolve omitted widths from the widget's pixel preference. */
  public function pixelWidthSize(int $viewportWidth, int $viewportHeight): string {
    if ($this->width === 'auto') {
      return (string)$this->naturalPixelWidth($viewportWidth, $viewportHeight);
    }
    if ($this->width !== '') {
      return $this->width;
    }
    $preferred = $this->instance->preferredPixelWidth();
    if ($preferred === null) {
      return '1*';
    }
    return (string)$this->naturalPixelWidth($viewportWidth, $viewportHeight);
  }

  public function naturalPixelWidth(int $viewportWidth, int $viewportHeight): int {
    $preferred = $this->instance->preferredPixelWidth();
    if ($preferred === null) {
      throw new \LogicException('Auto width needs a pixel width preference.');
    }
    $box = $this->box ?? new PixelBox();
    $margin = PixelBox::edges($box->margin, $viewportWidth, $viewportHeight);
    $border = PixelBox::edges($box->borderWidth, $viewportWidth, $viewportHeight);
    $padding = PixelBox::edges($box->padding, $viewportWidth, $viewportHeight);
    return $preferred + $margin['left'] + $margin['right'] + $border['left'] + $border['right'] + $padding['left'] + $padding['right'];
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
