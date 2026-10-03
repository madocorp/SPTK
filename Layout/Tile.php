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

}
