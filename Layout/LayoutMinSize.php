<?php

namespace SPTK\Layout;

/** Gives a weighted tile the smallest size it keeps when its parent has to scroll. */
trait LayoutMinSize {

  private ?int $minSize = null;

  /** Set the minimum cells on the parent's axis; null uses one cell. */
  public function setMinSize(?int $cells): void {
    if ($cells !== null && $cells < 0) {
      throw new \InvalidArgumentException('Minimum size cannot be negative.');
    }
    $this->minSize = $cells;
  }

  public function minSize(): ?int {
    return $this->minSize;
  }

}
