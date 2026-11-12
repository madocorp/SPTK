<?php

namespace SPTK\Layout;

use SPTK\Core\Color;
use SPTK\Widgets\Empty\Placeholder;

/** Measures an invisible-to-navigation placeholder over a grid layout's unused end. */
trait LayoutNodeRemainder {

  private ?LayoutLeaf $remainder = null;
  private ?Color $gridBackground = null;

  /** Set the inherited background used for unoccupied grid cells. */
  public function setGridBackground(Color $background): void {
    $this->gridBackground = $background;
  }

  /** Fill the portion of a grid tile left beyond its measured children. */
  private function measureRemainder(array $grids, Tile $grid, ?Tile $viewport): void {
    $end = $this->direction === 'horizontal' ? $grid->x : $grid->y;
    foreach ($grids as $childGrid) {
      $end = max($end, $this->direction === 'horizontal'
        ? $childGrid->x + $childGrid->width
        : $childGrid->y + $childGrid->height);
    }
    $limit = $this->direction === 'horizontal' ? $grid->x + $grid->width : $grid->y + $grid->height;
    if ($end >= $limit) {
      return;
    }
    $this->remainder = new LayoutLeaf('Empty', '', '', new Placeholder($this->gridBackground ?? new Color(0, 0, 170)), navigate: false);
    $this->remainder->setGrid($this->direction === 'horizontal'
      ? new Tile($end, $grid->y, $limit - $end, $grid->height)
      : new Tile($grid->x, $end, $grid->width, $limit - $end));
    $this->remainder->setViewport($viewport);
  }

}
