<?php

namespace SPTK\Rendering;

use SPTK\Core\{Cell, Color};
use SPTK\Layout\Tile;

/** Gives a widget local coordinates and clips complete glyphs to its tile. */
final class GridWriter {

  public function __construct(private Grid $grid, private Tile $tile, private ?Tile $clip = null) {
  }

  public function width(): int {
    return $this->tile->width;
  }

  public function height(): int {
    return $this->tile->height;
  }

  /** Read a painted cell using widget-local coordinates. */
  public function cellAt(int $x, int $y): Cell {
    if ($x < 0 || $x >= $this->width() || $y < 0 || $y >= $this->height()) {
      throw new \OutOfBoundsException('Cell outside widget tile.');
    }
    return $this->grid->cell($this->tile->x + $x, $this->tile->y + $y);
  }

  /** Return a clipped writer for the rows below a fixed top margin. */
  public function below(int $rows): self {
    if ($rows < 0 || $rows > $this->tile->height) {
      throw new \InvalidArgumentException('Top margin must fit inside the tile.');
    }
    return new self($this->grid, new Tile($this->tile->x, $this->tile->y + $rows, $this->tile->width, $this->tile->height - $rows), $this->clip);
  }

  /** Return a writer clipped to a rectangle within this writer. */
  public function region(int $x, int $y, int $width, int $height): self {
    if ($x < 0 || $y < 0 || $width < 0 || $height < 0
      || $x + $width > $this->tile->width || $y + $height > $this->tile->height) {
      throw new \InvalidArgumentException('Region must fit inside the tile.');
    }
    return new self($this->grid, new Tile($this->tile->x + $x, $this->tile->y + $y, $width, $height), $this->clip);
  }

  public function set(int $x, int $y, string $glyph, ?Color $fg = null, ?Color $bg = null): void {
    if (count(TextMetrics::glyphs($glyph)) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $glyph)) {
      throw new \InvalidArgumentException('A cell needs one printable grapheme.');
    }
    $this->put($x, $y, $this->cell($x, $y, $glyph, $fg, $bg));
  }

  /** Clip a prepared cell using its stored column width, with no text measurement. */
  public function put(int $x, int $y, Cell $cell): void {
    if ($cell->width === 0) {
      throw new \InvalidArgumentException('Continuations are managed by Grid.');
    }
    $width = $cell->width;
    $visible = $this->clip === null ? $this->tile : $this->tile->intersect($this->clip);
    $left = max(0, $visible->x);
    $right = min($this->grid->width(), $visible->x + $visible->width);
    $x += $this->tile->x;
    $y += $this->tile->y;
    if ($y < $visible->y || $y >= $visible->y + $visible->height || $y < 0 || $y >= $this->grid->height()) {
      return;
    }
    if ($x >= $left && $x + $width <= $right) {
      $this->grid->put($x, $y, $cell);
    } else {
      // A clipped half of a wide glyph becomes a colored blank, never a fragment.
      for ($column = max($left, $x); $column < min($right, $x + $width); $column++) {
        $this->grid->put($column, $y, new Cell(' ', $cell->fg, $cell->bg));
      }
    }
  }

  public function write(int $x, int $y, string $text, ?Color $fg = null, ?Color $bg = null): void {
    if (preg_match('/[\x00-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Text contains control characters.');
    }
    foreach (TextMetrics::glyphs($text) as $glyph) {
      $cell = $this->cell($x, $y, $glyph, $fg, $bg);
      $this->put($x, $y, $cell);
      $x += $cell->width;
      if ($x >= $this->width()) {
        break;
      }
    }
  }

  public function fill(Color $fg, Color $bg): void {
    $blank = new Cell(' ', $fg, $bg);
    for ($y = 0; $y < $this->height(); $y++) {
      for ($x = 0; $x < $this->width(); $x++) {
        $this->put($x, $y, $blank);
      }
    }
  }

  /** Fill one local row without touching the rest of the widget tile. */
  public function fillRow(int $y, Color $fg, Color $bg): void {
    $blank = new Cell(' ', $fg, $bg);
    for ($x = 0; $x < $this->width(); $x++) {
      $this->put($x, $y, $blank);
    }
  }

  private function cell(int $x, int $y, string $glyph, ?Color $fg, ?Color $bg): Cell {
    $column = max(0, $this->tile->x, $this->tile->x + $x);
    $row = $this->tile->y + $y;
    $previous = $column < $this->grid->width() && $row >= 0 && $row < $this->grid->height()
      ? $this->grid->cell($column, $row) : new Cell();
    return new Cell(
      $glyph, $fg ?? $previous->fg, $bg ?? $previous->bg,
      TextMetrics::glyphWidth($glyph),
    );
  }

}
