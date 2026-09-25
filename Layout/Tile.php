<?php

namespace SPTK\Layout;

/** A rectangle in window-grid coordinates or pixels. */
final readonly class Tile {

  public function __construct(
    public int $x,
    public int $y,
    public int $width,
    public int $height
  ) {
    if ($width < 0 || $height < 0) {
      throw new \InvalidArgumentException('Size cannot be negative.');
    }
  }

}
