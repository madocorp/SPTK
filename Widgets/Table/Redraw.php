<?php

namespace SPTK\Widgets\Table;

/** Tracks table rows affected by a cursor move without viewport scrolling. */
final class Redraw {

  private ?array $rows = null;

  /** Require a complete repaint after scrolling or source changes. */
  public function full(): void {
    $this->rows = null;
  }

  /** Clear pending changes after a repaint. */
  public function painted(): void {
    $this->rows = [];
  }

  /** Mark the previous and current cursor rows for a small repaint. */
  public function moved(int $previous, int $current): void {
    if ($this->rows !== null) {
      $this->rows[$previous] = true;
      $this->rows[$current] = true;
    }
  }

  /** Return pending row indices, or null when a full paint is needed. */
  public function rows(): ?array {
    return $this->rows === null ? null : array_keys($this->rows);
  }

}
