<?php

namespace SPTK\Widgets\List;

/** Tracks list rows that can be repainted without rebuilding the viewport. */
final class Redraw {

  private ?array $rows = null;

  /** Require a full tile paint after data, filtering, or scrolling changes. */
  public function full(): void {
    $this->rows = null;
  }

  /** Clear pending changes after a full or partial paint. */
  public function painted(): void {
    $this->rows = [];
  }

  /** Mark the old and new cursor rows when the viewport stays in place. */
  public function moved(int $oldPosition, int $oldScroll, int $position, int $scroll): void {
    if ($oldScroll !== $scroll) {
      $this->full();
      return;
    }
    if ($this->rows !== null && $oldPosition !== $position) {
      $this->rows[$oldPosition - $scroll] = true;
      $this->rows[$position - $scroll] = true;
    }
  }

  /** Mark a selected row whose value changed without moving the cursor. */
  public function current(int $position, int $scroll): void {
    if ($this->rows !== null) {
      $this->rows[$position - $scroll] = true;
    }
  }

  /** Return local row indices, or null when the whole tile needs painting. */
  public function rows(): ?array {
    return $this->rows === null ? null : array_keys($this->rows);
  }

}
