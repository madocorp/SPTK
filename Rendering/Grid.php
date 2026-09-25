<?php

namespace SPTK\Rendering;

use SPTK\Core\{Cell, Color};
use SPTK\Layout\Tile;

/** The window's character storage; widgets write through a clipped GridWriter. */
final class Grid {

  private array $cells = [];

  public function __construct(private int $width, private int $height) {
    $this->resize($width, $height);
  }

  public function width(): int {
    return $this->width;
  }

  public function height(): int {
    return $this->height;
  }

  public function resize(int $width, int $height): void {
    if ($width < 0 || $height < 0) {
      throw new \InvalidArgumentException('Grid size cannot be negative.');
    }
    $this->width = $width;
    $this->height = $height;
    $this->clear();
  }

  public function clear(): void {
    $this->cells = array_fill(0, $this->height, array_fill(0, $this->width, new Cell()));
  }

  public function cell(int $x, int $y): Cell {
    if (!isset($this->cells[$y][$x])) {
      throw new \OutOfBoundsException("Cell outside grid: {$x}, {$y}");
    }
    return $this->cells[$y][$x];
  }

  public function set(int $x, int $y, string $glyph, ?Color $fg = null, ?Color $bg = null): void {
    if (!isset($this->cells[$y][$x])) {
      return;
    }
    if (count(TextMetrics::glyphs($glyph)) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $glyph)) {
      throw new \InvalidArgumentException('A cell needs one printable grapheme.');
    }
    $this->put($x, $y, new Cell(
      $glyph, $fg ?? $this->cells[$y][$x]->fg, $bg ?? $this->cells[$y][$x]->bg,
      TextMetrics::glyphWidth($glyph),
    ));
  }

  /** Darken every rendered cell within a tile. */
  public function darken(Tile $tile): void {
    $right = min($this->width, $tile->x + $tile->width);
    $bottom = min($this->height, $tile->y + $tile->height);
    for ($y = max(0, $tile->y); $y < $bottom; $y++) {
      for ($x = max(0, $tile->x); $x < $right; $x++) {
        $cell = $this->cells[$y][$x];
        $this->cells[$y][$x] = new Cell($cell->glyph, $cell->fg->darkened(), $cell->bg->darkened(), $cell->width);
      }
    }
  }

  /** Store an already prepared cell without inspecting or measuring its glyph. */
  public function put(int $x, int $y, Cell $cell): void {
    if ($cell->width === 0) {
      throw new \InvalidArgumentException('Continuations are managed by Grid.');
    }
    if (!isset($this->cells[$y][$x])) {
      return;
    }
    if ($cell->width === 2 && $x + 1 >= $this->width) {
      $cell = new Cell(' ', $cell->fg, $cell->bg);
    }
    $width = $cell->width;
    // Clear both halves of every old wide glyph touched by this write.
    for ($column = $x; $column < $x + $width; $column++) {
      $old = $this->cells[$y][$column];
      if ($old->width === 1) {
        continue;
      }
      $lead = $old->width === 0 ? $column - 1 : $column;
      for ($part = $lead; $part <= $lead + 1; $part++) {
        $previous = $this->cells[$y][$part];
        $this->cells[$y][$part] = new Cell(' ', $previous->fg, $previous->bg);
      }
    }
    $this->cells[$y][$x] = $cell;
    if ($width === 2) {
      $this->cells[$y][$x + 1] = new Cell('', $cell->fg, $cell->bg, 0);
    }
  }

}
