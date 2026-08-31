<?php

namespace SPTK\Core;

/**
 * Describes an integer rectangle used for layout, clipping, and drawing.
 */
class Rect {

  public function __construct(
    public int $x,
    public int $y,
    public int $width,
    public int $height
  ) {
  }

  public function right(): int {
    return $this->x + $this->width;
  }

  public function bottom(): int {
    return $this->y + $this->height;
  }

  public function inset(int $left, int $top, ?int $right = null, ?int $bottom = null): self {
    $right ??= $left;
    $bottom ??= $top;
    return new self(
      $this->x + $left,
      $this->y + $top,
      max(0, $this->width - $left - $right),
      max(0, $this->height - $top - $bottom)
    );
  }

  public function intersect(self $other): ?self {
    $x1 = max($this->x, $other->x);
    $y1 = max($this->y, $other->y);
    $x2 = min($this->right(), $other->right());
    $y2 = min($this->bottom(), $other->bottom());
    if ($x2 <= $x1 || $y2 <= $y1) {
      return null;
    }
    return new self($x1, $y1, $x2 - $x1, $y2 - $y1);
  }

}
