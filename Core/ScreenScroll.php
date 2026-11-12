<?php

namespace SPTK\Core;

use SPTK\Layout\LayoutLeaf;
use SPTK\Rendering\TextMetrics;

/** Moves focus through scrolled layouts and returns their scroll marks. */
trait ScreenScroll {

  /** Return [x, y, Cell] scroll marks to draw over separators and tile edges. */
  public function scrollMarks(): array {
    $color = (new Style())->highlight;
    $cells = [];
    foreach ($this->layout->scrollMarks() as [$x, $y, $glyph]) {
      $cells[] = [$x, $y, new Cell($glyph, $color, $this->background, TextMetrics::glyphWidth($glyph))];
    }
    return $cells;
  }

  /** Move inside the scrolled layouts around a leaf, innermost first. */
  private function moveInScrollers(?LayoutLeaf $leaf, string $direction): bool {
    if ($leaf === null) {
      return false;
    }
    foreach ($this->layout->scrollersAround($leaf, $direction) as $scroller) {
      if ($this->focus->move($direction, $scroller->containsLeaf(...))) {
        return true;
      }
    }
    return false;
  }

  /** Accept a leaf with at least one visible cell. */
  private function isShown(LayoutLeaf $leaf): bool {
    $visible = $leaf->visibleGrid();
    return $visible->width > 0 && $visible->height > 0;
  }

  /** Measure again when the selection is inside a scrolled layout, so it scrolls into view. */
  private function revealSelection(): void {
    $leaf = $this->focus->selectedLeaf();
    if ($leaf !== null && $this->layout->insideScroller($leaf)) {
      $this->layoutChanged = true;
    }
  }

}
