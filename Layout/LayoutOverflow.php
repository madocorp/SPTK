<?php

namespace SPTK\Layout;

/** Track a vertical layout's scroll offset and shift full child tiles through its viewport. */
final class LayoutOverflow {

  private int $offset = 0;
  private int $requested = 0;
  private int $maximum = 0;

  /** Store a requested row offset until child heights are measured. */
  public function setOffset(int $rows): void {
    $this->requested = max(0, $rows);
  }

  /** Return the row offset used by the latest measurement. */
  public function offset(): int {
    return $this->offset;
  }

  /** Return the latest measured maximum row offset. */
  public function maximum(): int {
    return $this->maximum;
  }

  /** Intersect this layout's rectangle with an ancestor overflow viewport. */
  public function viewport(Tile $grid, ?Tile $parent): Tile {
    return $parent === null ? $grid : $parent->intersect($grid);
  }

  /** Keep full child heights and shift them by the clamped row offset. */
  public function shift(Tile $grid, array $tiles): array {
    if ($tiles === []) {
      $this->maximum = $this->offset = 0;
      return [];
    }
    $last = $tiles[count($tiles) - 1];
    $this->maximum = max(0, $last->y + $last->height - $grid->y - $grid->height);
    $this->offset = min($this->requested, $this->maximum);
    return array_map(fn(Tile $tile): Tile => new Tile($tile->x, $tile->y - $this->offset, $tile->width, $tile->height), $tiles);
  }

}
