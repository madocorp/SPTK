<?php

namespace SPTK\Layout;

use SPTK\Core\Color;

/** Splits a grid tile into child layouts and widgets and measures their separator areas. */
final class LayoutNode {

  use LayoutDeckTile;
  use LayoutNodeDeck;
  use LayoutNodeLookup;
  use LayoutNodePixelSize;
  use LayoutNodeSeparators;
  use LayoutOverwriteTile;

  private $children = [];
  private $grid;
  private ?Tile $pixelTile = null;
  private ?Tile $pixelBorder = null;
  private ?Tile $pixelBackgroundArea = null;
  private ?Tile $pixelContent = null;
  private ?Color $pixelParentBackground = null;
  private ?Color $pixelBackground = null;
  private ?LayoutLeaf $focus = null;
  private ?LayoutOverflow $overflow = null;
  private ?Tile $viewport = null;

  public function __construct(private string $direction, private string $width, private string $height, bool $navigateChildren = true, private ?string $id = null, ?string $tip = null, private bool $navigate = true, private bool $enterChildren = false, private bool $pixelMode = false, private ?PixelBox $box = null, private ?Color $outerBackground = null, private ?string $initialFocus = null) {
    if ($enterChildren && $navigateChildren) {
      throw new \InvalidArgumentException('enterChildren requires navigateChildren="false".');
    }
    if (!$navigateChildren) {
      $widget = new \SPTK\Widgets\Empty\Placeholder(new \SPTK\Core\Color(0, 0, 0));
      $widget->setId($id);
      $widget->setTips($tip ?? ($enterChildren ? 'Return enters this layout; Esc leaves it.' : null));
      $this->focus = new LayoutLeaf('Layout', $width, $height, $widget);
    }
  }

  public function setGrid($grid) {
    $this->grid = $grid;
    $this->focus?->setGrid($grid);
  }

  public function grid(): Tile {
    return $this->grid;
  }

  public function pixelContent(): ?Tile {
    return $this->pixelContent;
  }

  public function pixelTile(): ?Tile {
    return $this->pixelTile;
  }

  /** Enable vertical overflow so fixed-height children retain their full tiles. */
  public function setOverflow(bool $overflow): void {
    if ($overflow && ($this->direction !== 'vertical' || $this->pixelMode)) {
      throw new \InvalidArgumentException('Overflow requires a vertical grid layout.');
    }
    $this->overflow = $overflow ? new LayoutOverflow() : null;
  }

  /** Set the requested row offset, clamped on the next measurement. */
  public function setScrollOffset(int $rows): void {
    $this->overflow?->setOffset($rows);
  }

  /** Return the last measured maximum row offset. */
  public function maxScrollOffset(): int {
    return $this->overflow?->maximum() ?? 0;
  }

  /** Return the row offset used by the latest measurement. */
  public function scrollOffset(): int {
    return $this->overflow?->offset() ?? 0;
  }

  /** Apply a local Style to this pixel node's own box. */
  public function setPixelBox(PixelBox $box): void {
    $this->box = $box;
  }

  public function name() {
    return $this->direction;
  }

  public function width() {
    return $this->overwriteAdjustedSize($this->deckAdjustedSize($this->width, 'width'), 'width');
  }

  public function height() {
    return $this->overwriteAdjustedSize($this->deckAdjustedSize($this->height, 'height'), 'height');
  }

  public function tileId(): ?string {
    return $this->id;
  }

  /** Return this layout's focus tile when child navigation is grouped. */
  public function focusLeaf(): ?LayoutLeaf {
    return $this->focus;
  }

  /** Return the outer focus tile when this layout is an enterable group. */
  public function entryFocus(): ?LayoutLeaf {
    return $this->enterChildren ? $this->focus : null;
  }

  /** Return the preferred child when this group is entered. */
  public function initialFocus(): ?LayoutLeaf {
    $leaf = $this->initialFocus === null ? null : $this->findTile($this->initialFocus);
    return $leaf instanceof LayoutLeaf && in_array($leaf, $this->childMovementLeaves(), true) ? $leaf : null;
  }

  /** Measure stacked or side-by-side children at a known width. */
  public function naturalHeight(int $width): int {
    if ($this->direction === 'horizontal') {
      $children = array_values(array_filter($this->visibleChildren(), fn(mixed $child): bool => !$child instanceof LayoutSeparator));
      if ($children === []) {
        return 0;
      }
      $grids = Splitter::horizontal(new Tile(0, 0, $width, 0), array_map(fn(mixed $child): string => $child->width(), $children));
      $heights = [];
      foreach ($children as $index => $child) {
        $heights[] = $child->naturalHeight($grids[$index]->width);
      }
      return max($heights);
    }
    $height = 0;
    $hasVisibleChild = false;
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $size = $child->height();
      if ($hasVisibleChild && $size !== '0*' && $size !== '0') {
        $height++;
      }
      if ($size === 'auto') {
        $height += $child->naturalHeight($width);
      } else if (str_ends_with($size, '*')) {
        $height += $size === '0*' ? 0 : max(1, (int)$size);
      } else if (ctype_digit($size)) {
        $height += (int)$size;
      } else {
        throw new \LogicException('Intrinsic layout height requires fixed, weighted, or auto child heights.');
      }
      if ($size !== '0*' && $size !== '0') {
        $hasVisibleChild = true;
      }
    }
    return $height;
  }

  public function addNode(LayoutNode $node): void {
    $this->openFirstDeckTile($node);
    $this->children[] = $node;
  }

  public function addLeaf(LayoutLeaf $leaf): void {
    $this->openFirstDeckTile($leaf);
    $this->children[] = $leaf;
  }

  public function addSeparator(LayoutSeparator $separator): void {
    $this->children[] = $separator;
  }

  /** Return children as shown: without hidden overwrites and the separators they leave at an edge or doubled. */
  private function visibleChildren(): array {
    $visible = [];
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        if ($visible !== [] && !end($visible) instanceof LayoutSeparator) {
          $visible[] = $child;
        }
      } else if (!$child->isOverwriter()) {
        $visible[] = self::shownTile($child);
      }
    }
    if (end($visible) instanceof LayoutSeparator) {
      array_pop($visible);
    }
    return $visible;
  }

  /** Replace a child subtree or leaf while retaining the other layout items and their widgets. */
  public function replaceChild(LayoutNode|LayoutLeaf $current, LayoutNode|LayoutLeaf $replacement): bool {
    foreach ($this->children as $index => $child) {
      if ($child === $current) {
        $this->children[$index] = $replacement;
        return true;
      }
      if ($child instanceof self && $child->replaceChild($current, $replacement)) {
        return true;
      }
    }
    return false;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid, ?Tile $viewport = null) {
    $this->grid = $grid;
    $this->pixelTile = null;
    $this->focus?->setGrid($grid);
    if ($this->overflow !== null) {
      $viewport = $this->overflow->viewport($grid, $viewport);
    }
    $this->viewport = $viewport;
    if ($this->pixelMode) {
      foreach ($this->visibleChildren() as $child) {
        if ($child instanceof self) {
          $child->assignGrid($grid);
        } else if ($child instanceof LayoutLeaf) {
          $child->setGrid($grid);
        }
      }
      return;
    }
    // Check the XML order on all children; hidden elements still count as layout items here.
    foreach ($this->children as $index => $child) {
      if ($child instanceof LayoutSeparator && ($index === 0 || $index === count($this->children) - 1 || $this->children[$index - 1] instanceof LayoutSeparator || $this->children[$index + 1] instanceof LayoutSeparator)) {
        throw new \RuntimeException('Separator must be placed between two layout items.');
      }
    }
    $content = array_filter($this->visibleChildren(), fn(mixed $child): bool => !$child instanceof LayoutSeparator);
    if ($this->direction === 'horizontal') {
      $widths = [];
      foreach ($content as $child) {
        $widths[] = $child->width();
      }
      $grids = Splitter::horizontal($grid, $widths);
    } else {
      $heights = [];
      foreach ($content as $child) {
        $size = $child->height();
        $heights[] = $size === 'auto' ? (string)$child->naturalHeight($grid->width) : $size;
      }
      $grids = Splitter::vertical($grid, $heights);
      if ($this->overflow !== null) {
        $grids = $this->overflow->shift($grid, $grids);
      }
    }
    $gridIndex = 0;
    foreach ($content as $child) {
      $child->setGrid($grids[$gridIndex]);
      if ($child instanceof self) {
        $child->measureGrid($grids[$gridIndex], $viewport);
      } else {
        $child->setViewport($viewport);
      }
      $gridIndex++;
    }
  }

  /** Retain a grid rectangle only for navigation while descendants use pixel rectangles. */
  private function assignGrid(Tile $grid): void {
    $this->grid = $grid;
    $this->pixelTile = null;
    $this->focus?->setGrid($grid);
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        $child->assignGrid($grid);
      } else if ($child instanceof LayoutLeaf) {
        $child->setGrid($grid);
      }
    }
  }

  /** Measure the exact pixel box and split only its inner rectangle. */
  public function measurePixel(Tile $tile, int $viewportWidth, int $viewportHeight, Color $parentBackground): void {
    $this->pixelTile = $tile;
    $this->focus?->setNavigationPixelTile($tile);
    $this->pixelParentBackground = $parentBackground;
    $this->pixelBackground = $this->box?->background ?? $parentBackground;
    [$this->pixelBorder, $this->pixelBackgroundArea, $this->pixelContent] = ($this->box ?? new PixelBox())->areas($tile, $viewportWidth, $viewportHeight);
    $content = [];
    $sizes = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof LayoutSeparator) {
        throw new \LogicException('Pixel layouts use border widths and explicit spacing, not separators.');
      }
      $content[] = $child;
      if ($this->direction === 'horizontal') {
        $size = $child instanceof LayoutLeaf ? $child->pixelWidthSize($viewportWidth, $viewportHeight) : $child->width();
        $sizes[] = $size === 'auto' ? (string)$child->naturalPixelWidth($viewportWidth, $viewportHeight) : $size;
      } else {
        $size = $child->height();
        $sizes[] = $size === 'auto' ? (string)$child->naturalPixelHeight($this->pixelContent->width, $viewportWidth, $viewportHeight) : $size;
      }
    }
    foreach (PixelSplitter::split($this->pixelContent, $this->direction, $sizes, $viewportWidth, $viewportHeight) as $index => $childTile) {
      $content[$index]->measurePixel($childTile, $viewportWidth, $viewportHeight, $this->pixelBackground);
    }
  }

  /** Paint children, or only the summary while this tile or a deck ancestor is closed. */
  public function paint(\SPTK\Rendering\Grid $grid, ?LayoutLeaf $selected = null, bool $closed = false): void {
    if ($this->pixelTile !== null) {
      return;
    }
    if ($selected === $this->focus) {
      $selected = null;
    }
    $closed = $closed || $this->isDeckClosed();
    if ($closed && $this->paintDeckSummary(new \SPTK\Rendering\GridWriter($grid, $this->grid, $this->viewport), $this->id)) {
      if ($selected !== null && !$this->containsLeaf($selected)) {
        $grid->darken($this->viewport === null ? $this->grid : $this->grid->intersect($this->viewport));
      }
      return;
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      if ($child instanceof self) {
        $child->paint($grid, $selected, $closed);
      } else {
        $child->paint($grid, $selected === null || $child === $selected, $closed);
      }
    }
  }

  /** Measure child backgrounds and separator areas in the shared window coordinate system. */
  public function measureArea(Tile $grid, WindowGeometry $geometry, bool $root = false): void {
    if ($this->pixelMode) {
      $tile = $root
        ? new Tile(0, 0, $geometry->windowWidth, $geometry->windowHeight)
        : $geometry->backgroundArea($this->grid, $grid);
      $background = $this->outerBackground ?? new Color(32, 38, 48);
      $this->measurePixel($tile, $tile->width, $tile->height, $background);
      return;
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $child->measureArea($grid, $geometry);
    }
    $children = $this->visibleChildren();
    foreach ($children as $index => $child) {
      if ($child instanceof LayoutSeparator) {
        $this->measureSeparatorArea($child, $children[$index - 1], $grid, $geometry);
      }
    }
  }

  /** Measure one separator after its preceding child within this layout's padded area. */
  private function measureSeparatorArea(LayoutSeparator $separator, LayoutNode|LayoutLeaf $before, Tile $windowGrid, WindowGeometry $geometry): void {
    $area = $geometry->separatorArea($this->grid, $before->grid(), $windowGrid, $this->direction);
    $separator->setArea($this->viewport === null ? $area : $area->intersect($geometry->backgroundArea($this->viewport, $windowGrid)));
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer, ?LayoutLeaf $selected = null): void {
    if ($this->pixelTile !== null) {
      $active = $selected === null || $selected === $this->focus;
      $parentBackground = $active ? $this->pixelParentBackground : $this->pixelParentBackground->darkened();
      $borderColor = $this->box?->borderColor ?? new Color(71, 85, 104);
      $background = $active ? $this->pixelBackground : $this->pixelBackground->darkened();
      $renderer->fill($this->pixelTile, $parentBackground);
      $renderer->fill($this->pixelBorder, $active ? $borderColor : $borderColor->darkened());
      $renderer->fill($this->pixelBackgroundArea, $background);
    }
    if ($selected === $this->focus) {
      $selected = null;
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        $child->drawBackgrounds($renderer, $selected);
      } else if (!$child instanceof LayoutSeparator) {
        $child->drawBackground($renderer, $selected === null || $child === $selected);
      }
    }
  }

  /** Return rendered leaves or navigation targets, treating grouped layouts as single tiles. */
  public function leaves(bool $navigationOnly = false): array {
    if ($navigationOnly && $this->focus !== null) {
      return [$this->focus];
    }
    $leaves = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->leaves($navigationOnly));
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Return the focus targets inside this grouped layout. */
  public function childNavigationLeaves(): array {
    $leaves = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->leaves(true));
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Return arrow destinations inside this grouped layout. */
  public function childMovementLeaves(): array {
    $leaves = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->movementLeaves());
      } else if ($child instanceof LayoutLeaf && $child->navigate()) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Find an enterable grouped layout by its focus tile. */
  public function enterableFor(?LayoutLeaf $focus): ?self {
    if ($focus !== null && $this->focus === $focus && $this->enterChildren) {
      return $this;
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        $found = $child->enterableFor($focus);
        if ($found !== null) {
          return $found;
        }
      }
    }
    return null;
  }

  /** Return focus tiles eligible as destinations during arrow movement. */
  public function movementLeaves(): array {
    if (!$this->navigate) {
      return [];
    }
    if ($this->focus !== null) {
      return [$this->focus];
    }
    return $this->childMovementLeaves();
  }

  /** Resolve a focused layout to its rendered descendants for pixel selection colors. */
  public function focusedLeaves(?LayoutLeaf $selected): array {
    if ($this->focus !== null && $selected === $this->focus) {
      return $this->leaves();
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        $leaves = $child->focusedLeaves($selected);
        if ($leaves !== []) {
          return $leaves;
        }
      }
    }
    return [];
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->direction}\n";
    foreach ($this->children as $child) {
      $child->debug($level + 1);
    }
  }

}
