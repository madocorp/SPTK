<?php

namespace SPTK\Layout;

/** Holds a window's pixel metrics and converts grid tiles into content, background, and separator areas. */
final readonly class WindowGeometry {

  /** Capture cell and window sizes with the centered grid offset for one layout measurement. */
  public function __construct(
    public int $cellWidth,
    public int $cellHeight,
    public int $windowWidth,
    public int $windowHeight,
    public int $offsetX,
    public int $offsetY,
  ) {
  }

  /** Convert a grid tile into the pixel rectangle occupied by its cells. */
  public function pixelArea(Tile $grid): Tile {
    return new Tile(
      $grid->x * $this->cellWidth + $this->offsetX,
      $grid->y * $this->cellHeight + $this->offsetY,
      $grid->width * $this->cellWidth,
      $grid->height * $this->cellHeight,
    );
  }

  /** Extend a grid tile through its padding and out to any adjoining window edges. */
  public function backgroundArea(Tile $grid, Tile $windowGrid): Tile {
    $left = 0;
    if ($grid->x !== 0) {
      $left = $grid->x * $this->cellWidth + $this->offsetX - $this->cellWidth;
    }
    $top = 0;
    if ($grid->y !== 0) {
      $top = $grid->y * $this->cellHeight + $this->offsetY - intdiv($this->cellHeight, 2);
    }
    $right = $this->windowWidth;
    if ($grid->x + $grid->width < $windowGrid->width) {
      $right = ($grid->x + $grid->width) * $this->cellWidth + $this->offsetX + $this->cellWidth;
    }
    $bottom = $this->windowHeight;
    if ($grid->y + $grid->height < $windowGrid->height) {
      $bottom = ($grid->y + $grid->height) * $this->cellHeight + $this->offsetY + intdiv($this->cellHeight + 1, 2);
    }
    return new Tile($left, $top, $right - $left, $bottom - $top);
  }

  /** Place a two-pixel separator after a child across its parent's padded area. */
  public function separatorArea(Tile $parent, Tile $before, Tile $windowGrid, string $direction): Tile {
    $area = $this->backgroundArea($parent, $windowGrid);
    $cells = $this->pixelArea($before);
    if ($direction === 'horizontal') {
      $boundary = $cells->x + $cells->width + $this->cellWidth;
      return new Tile($boundary - 1, $area->y, 2, $area->height);
    }
    $boundary = $cells->y + $cells->height + intdiv($this->cellHeight + 1, 2);
    return new Tile($area->x, $boundary - 1, $area->width, 2);
  }

}
