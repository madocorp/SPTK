<?php

namespace SPTK\Layout;

/** A rectangle in window-grid coordinates or pixels. */
final readonly class Tile {

  public int $width;
  public int $height;

  /** Keep positions unchanged and collapse negative dimensions into an empty rectangle. */
  public function __construct(
    public int $x,
    public int $y,
    int $width,
    int $height
  ) {
    $this->width = max(0, $width);
    $this->height = max(0, $height);
  }

  /** Return the visible overlap of two rectangles without moving either source. */
  public function intersect(self $other): self {
    $left = max($this->x, $other->x);
    $top = max($this->y, $other->y);
    $right = min($this->x + $this->width, $other->x + $other->width);
    $bottom = min($this->y + $this->height, $other->y + $other->height);
    return new self($left, $top, $right - $left, $bottom - $top);
  }

}
