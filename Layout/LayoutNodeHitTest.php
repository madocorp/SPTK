<?php

namespace SPTK\Layout;

/** Finds the visible navigation tile under a window pixel position. */
trait LayoutNodeHitTest {

  /** Return the deepest clickable tile, respecting groups and overflow clipping. */
  public function hitTest(float $x, float $y, WindowGeometry $geometry): ?LayoutLeaf {
    if (!$this->navigate || !$this->hitArea($geometry)->contains($x, $y)) {
      return null;
    }
    if ($this->focus !== null && !$this->enterChildren) {
      return $this->focus;
    }
    foreach (array_reverse($this->visibleChildren()) as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      if ($child instanceof LayoutNode) {
        $leaf = $child->hitTest($x, $y, $geometry);
      } else {
        $area = $child->pixelTile() ?? $geometry->pixelArea($child->visibleGrid());
        $leaf = $child->navigate() && $area->contains($x, $y) ? $child : null;
      }
      if ($leaf !== null) {
        return $leaf;
      }
    }
    return $this->focus;
  }

  /** Clip a grid layout to its visible viewport, or use its measured pixel box. */
  private function hitArea(WindowGeometry $geometry): Tile {
    return $this->pixelTile ?? $geometry->pixelArea($this->viewport === null
      ? $this->grid : $this->grid->intersect($this->viewport));
  }

}
