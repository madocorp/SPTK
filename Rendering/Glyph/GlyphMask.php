<?php

namespace SPTK\Rendering\Glyph;

/** Stores a one-bit raster and exposes drawing primitives for geometry glyphs. */
final class GlyphMask {

  private array $pixels;

  /** Create an empty mask at the target font cell size. */
  public function __construct(private int $width, private int $height) {
    $this->pixels = array_fill(0, $width * $height, false);
  }

  /** Return the mask width in pixels. */
  public function width(): int {
    return $this->width;
  }

  /** Return the mask height in pixels. */
  public function height(): int {
    return $this->height;
  }

  /** Turn one mask pixel on or off when it lies inside the cell. */
  public function point(int $x, int $y, bool $on = true): void {
    if ($x >= 0 && $x < $this->width && $y >= 0 && $y < $this->height) {
      $this->pixels[$y * $this->width + $x] = $on;
    }
  }

  /** Fill a clipped rectangle in the mask. */
  public function rect(int $x, int $y, int $width, int $height): void {
    for ($py = max(0, $y); $py < min($this->height, $y + $height); $py++) {
      for ($px = max(0, $x); $px < min($this->width, $x + $width); $px++) {
        $this->point($px, $py);
      }
    }
  }

  /** Rasterize a weighted line with evenly sampled square brush points. */
  public function line(float $x1, float $y1, float $x2, float $y2, int $weight = 1): void {
    $steps = max(1, (int)ceil(max(abs($x2 - $x1), abs($y2 - $y1))));
    for ($step = 0; $step <= $steps; $step++) {
      $x = (int)round($x1 + ($x2 - $x1) * $step / $steps) - intdiv($weight, 2);
      $y = (int)round($y1 + ($y2 - $y1) * $step / $steps) - intdiv($weight, 2);
      $this->rect($x, $y, $weight, $weight);
    }
  }

  /** Convert set pixels into horizontal spans for efficient SDL fills. */
  public function spans(): array {
    $spans = [];
    for ($y = 0; $y < $this->height; $y++) {
      $start = null;
      for ($x = 0; $x <= $this->width; $x++) {
        $on = $x < $this->width && $this->pixels[$y * $this->width + $x];
        if ($on && $start === null) {
          $start = $x;
        } else if (!$on && $start !== null) {
          $spans[$y][] = [$start, $x - $start];
          $start = null;
        }
      }
    }
    return $spans;
  }

}
