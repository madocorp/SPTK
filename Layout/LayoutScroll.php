<?php

namespace SPTK\Layout;

/** Keeps one layout's scroll offset, stays at the end once there, and counts hidden tiles. */
final class LayoutScroll {

  private int $offset = 0;
  private int $maximum = 0;
  private int $hiddenBefore = 0;
  private int $hiddenAfter = 0;
  private bool $atEnd = false;

  /** Store a requested offset; the next measurement clamps it. */
  public function setOffset(int $cells): void {
    $this->offset = max(0, $cells);
    $this->atEnd = false;
  }

  public function offset(): int {
    return $this->offset;
  }

  public function maximum(): int {
    return $this->maximum;
  }

  /** Clamp the offset and keep an optional [start, end) range in view, aligned to the side it comes from. */
  public function place(int $end, int $view, ?array $reveal): int {
    $this->maximum = max(0, $end - $view);
    $offset = $this->atEnd ? $this->maximum : min($this->offset, $this->maximum);
    if ($reveal !== null) {
      [$start, $stop] = $reveal;
      if ($start < $offset) {
        $offset = $start;
      } else if ($stop > $offset + $view) {
        $offset = $stop - $view;
      }
    }
    $this->offset = max(0, min($offset, $this->maximum));
    $this->atEnd = $this->maximum > 0 && $this->offset === $this->maximum;
    return $this->offset;
  }

  /** Remember how many navigation tiles are cut off before and after the view. */
  public function setHidden(int $before, int $after): void {
    $this->hiddenBefore = $before;
    $this->hiddenAfter = $after;
  }

  public function hiddenBefore(): int {
    return $this->hiddenBefore;
  }

  public function hiddenAfter(): int {
    return $this->hiddenAfter;
  }

}
