<?php

namespace SPTK\Layout;

/** Scrolls a grid layout by whole tiles when its children do not fit, and marks the hidden side. */
trait LayoutNodeScroll {

  private ?LayoutScroll $scroll = null;
  private bool $scrolling = false;
  private array $scrollMarks = [];

  /** Scrolling is automatic now; kept so older layouts still load. */
  public function setOverflow(bool $overflow): void {
  }

  /** Request a scroll offset in cells; the next measurement clamps it. */
  public function setScrollOffset(int $cells): void {
    $this->scroller()->setOffset($cells);
  }

  public function scrollOffset(): int {
    return $this->scroll?->offset() ?? 0;
  }

  public function maxScrollOffset(): int {
    return $this->scroll?->maximum() ?? 0;
  }

  /** Report whether the children did not fit at the last measurement. */
  public function isScrolling(): bool {
    return $this->scrolling;
  }

  /** Return the scrolling layouts around a leaf on the direction's axis, innermost first. */
  public function scrollersAround(LayoutLeaf $leaf, string $direction): array {
    $axis = in_array($direction, ['up', 'down'], true) ? 'vertical' : 'horizontal';
    $nodes = array_filter([$this, ...($this->visiblePathTo($leaf) ?? [])], fn(mixed $tile): bool => $tile instanceof self
      && $tile->scrolling && $tile->direction === $axis);
    return array_reverse(array_values($nodes));
  }

  /** Report whether a leaf sits inside a layout that scrolls. */
  public function insideScroller(LayoutLeaf $leaf): bool {
    return $this->scrollersAround($leaf, 'up') !== [] || $this->scrollersAround($leaf, 'left') !== [];
  }

  /** Return [x, y, glyph] cells of the scroll marks in this subtree. */
  public function scrollMarks(): array {
    $marks = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof self) {
        array_push($marks, ...$child->scrollMarks());
      }
    }
    array_push($marks, ...$this->scrollMarks);
    return $marks;
  }

  /** Split the children, scroll them when they do not fit, measure them, and return their final tiles. */
  private function measureContent(Tile $grid, array $content, array $sizes, ?Tile $viewport, array $edges, ?LayoutLeaf $reveal): array {
    $this->scrollMarks = [];
    $grids = $this->splitContent($grid, $content, $sizes);
    if ($this->scrolling) {
      $this->viewport = $viewport === null ? $grid : $viewport->intersect($grid);
    }
    $childEdges = $this->childEdges($edges);
    $this->measureChildren($content, $grids, $childEdges, $reveal);
    if (!$this->scrolling) {
      return $grids;
    }
    $offset = $this->placeScroll($grid, $content, $reveal);
    if ($offset !== 0) {
      $vertical = $this->direction === 'vertical';
      $grids = array_map(fn(Tile $tile): Tile => $vertical
        ? new Tile($tile->x, $tile->y - $offset, $tile->width, $tile->height)
        : new Tile($tile->x - $offset, $tile->y, $tile->width, $tile->height), $grids);
      $this->measureChildren($content, $grids, $childEdges, $reveal);
    }
    $this->measureScrollMarks($grid, $viewport, $edges);
    return $grids;
  }

  /** Use normal weighted sizes when the minimums fit; otherwise give each child its minimum. No child is longer than this tile. */
  private function splitContent(Tile $grid, array $content, array $sizes): array {
    $vertical = $this->direction === 'vertical';
    $space = self::axisLength($grid, $vertical);
    $minimums = [];
    foreach ($content as $index => $child) {
      $minimum = min($space, $this->minimumSize($child, $sizes[$index], $space));
      $minimums[] = (string)$minimum;
      if (!str_ends_with($sizes[$index], '*')) {
        $sizes[$index] = (string)$minimum;
      }
    }
    $this->scrolling = $minimums !== [] && self::splitEnd($grid, $minimums, $vertical) > $space;
    if (!$this->scrolling) {
      $this->scroll?->setOffset(0);
      return $vertical ? Splitter::vertical($grid, $sizes) : Splitter::horizontal($grid, $sizes);
    }
    foreach ($content as $index => $child) {
      if (str_ends_with($sizes[$index], '*') && $sizes[$index] !== '0*' && $child->isDeckOpen()) {
        $minimums[$index] = (string)$space;
      }
    }
    return $vertical ? Splitter::vertical($grid, $minimums) : Splitter::horizontal($grid, $minimums);
  }

  /** Return the cells a child keeps when space runs out. */
  private function minimumSize(LayoutNode|LayoutLeaf $child, string $size, int $space): int {
    if ($size === '0' || $size === '0*') {
      return 0;
    }
    if (str_ends_with($size, '*')) {
      return $child->minSize() ?? 1;
    }
    return str_ends_with($size, '%') ? (int)round($space * (float)$size / 100) : (int)$size;
  }

  /** Measure the far end of fixed sizes laid out from the tile start. */
  private static function splitEnd(Tile $grid, array $sizes, bool $vertical): int {
    $origin = new Tile(0, 0, $grid->width, $grid->height);
    $end = 0;
    foreach ($vertical ? Splitter::vertical($origin, $sizes) : Splitter::horizontal($origin, $sizes) as $tile) {
      $end = max($end, self::axisEnd($tile, $vertical));
    }
    return $end;
  }

  /** Give each child the separator edges it touches, inheriting the edges it shares with this layout. */
  private function childEdges(array $edges): array {
    [$first, $last] = $this->direction === 'vertical' ? ['top', 'bottom'] : ['left', 'right'];
    $visible = $this->visibleChildren();
    $result = [];
    foreach ($visible as $index => $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $own = $edges;
      $own[$first] = $index === 0 ? ($edges[$first] ?? false) : $visible[$index - 1] instanceof LayoutSeparator;
      $own[$last] = $index === count($visible) - 1 ? ($edges[$last] ?? false) : $visible[$index + 1] instanceof LayoutSeparator;
      $result[] = $own;
    }
    return $result;
  }

  private function measureChildren(array $content, array $grids, array $edges, ?LayoutLeaf $reveal): void {
    foreach ($content as $index => $child) {
      $child->setGrid($grids[$index]);
      if ($child instanceof self) {
        $child->measureGrid($grids[$index], $this->viewport, $edges[$index], $reveal);
      } else {
        $child->setViewport($this->viewport);
      }
    }
  }

  /** Choose the offset from the unshifted child tiles. */
  private function placeScroll(Tile $grid, array $content, ?LayoutLeaf $reveal): int {
    $vertical = $this->direction === 'vertical';
    $origin = self::axisStart($grid, $vertical);
    $view = self::axisLength($grid, $vertical);
    $end = 0;
    foreach ($content as $child) {
      $end = max($end, self::axisEnd($child->grid(), $vertical) - $origin);
    }
    return $this->scroller()->place($end, $view, $reveal === null ? null : $this->revealRange($reveal, $origin));
  }

  /** Return the range of the direct child that holds a leaf. */
  private function revealRange(LayoutLeaf $leaf, int $origin): ?array {
    $path = $this->visiblePathTo($leaf);
    if ($path === null) {
      return null;
    }
    $vertical = $this->direction === 'vertical';
    $start = self::axisStart($path[0]->grid(), $vertical) - $origin;
    return [$start, $start + self::axisLength($path[0]->grid(), $vertical)];
  }

  /** Return shown tiles from a direct child down to a leaf or the group that owns it. */
  private function visiblePathTo(LayoutLeaf $leaf): ?array {
    foreach ($this->visibleChildren() as $child) {
      if ($child === $leaf || ($child instanceof self && $child->focus === $leaf)) {
        return [$child];
      }
      if ($child instanceof self && ($path = $child->visiblePathTo($leaf)) !== null) {
        return [$child, ...$path];
      }
    }
    return null;
  }

  /** Count hidden navigation tiles and place the marks on a separator or on the layout's own edge. */
  private function measureScrollMarks(Tile $grid, ?Tile $outer, array $edges): void {
    $view = $this->viewport;
    if ($view->width === 0 || $view->height === 0) {
      return;
    }
    $vertical = $this->direction === 'vertical';
    $before = 0;
    $after = 0;
    foreach ($this->childMovementLeaves() as $leaf) {
      $before += self::axisStart($leaf->grid(), $vertical) < self::axisStart($view, $vertical) ? 1 : 0;
      $after += self::axisEnd($leaf->grid(), $vertical) > self::axisEnd($view, $vertical) ? 1 : 0;
    }
    $this->scroller()->setHidden($before, $after);
    $offset = $this->scroller()->offset();
    if ($offset > 0) {
      $this->addScrollMark($grid, $outer, $edges, false, $vertical ? '▲' : '◀', $before);
    }
    if ($offset < $this->scroller()->maximum() || $after > 0) {
      $this->addScrollMark($grid, $outer, $edges, true, $vertical ? '▼' : '▶', $after);
    }
  }

  /** Place one mark on the separator above or below, or on the layout's own edge row. */
  private function addScrollMark(Tile $grid, ?Tile $outer, array $edges, bool $end, string $arrow, int $count): void {
    $vertical = $this->direction === 'vertical';
    $view = $this->viewport;
    $below = $vertical && $end;
    $row = $below ? $grid->y + $grid->height : $grid->y - 1;
    $shown = $below ? $view->y + $view->height === $grid->y + $grid->height : $view->y === $grid->y;
    $inside = $row >= 0 && ($outer === null || ($row >= $outer->y - 1 && $row <= $outer->y + $outer->height));
    $onSeparator = ($edges[$below ? 'bottom' : 'top'] ?? false) && $shown && $inside;
    if (!$onSeparator) {
      $row = $below ? $view->y + $view->height - 1 : $view->y;
    }
    $label = $count > 0 ? ($end ? $count . $arrow : $arrow . $count) : $arrow;
    $glyphs = mb_str_split($onSeparator ? ' ' . $label . ' ' : $label);
    if (count($glyphs) > $view->width) {
      return;
    }
    $left = !$vertical && !$end ? $view->x : $view->x + $view->width - count($glyphs);
    foreach ($glyphs as $index => $glyph) {
      $this->scrollMarks[] = [$left + $index, $row, $glyph];
    }
  }

  private function scroller(): LayoutScroll {
    return $this->scroll ??= new LayoutScroll();
  }

  private static function axisStart(Tile $tile, bool $vertical): int {
    return $vertical ? $tile->y : $tile->x;
  }

  private static function axisLength(Tile $tile, bool $vertical): int {
    return $vertical ? $tile->height : $tile->width;
  }

  private static function axisEnd(Tile $tile, bool $vertical): int {
    return self::axisStart($tile, $vertical) + self::axisLength($tile, $vertical);
  }

}
