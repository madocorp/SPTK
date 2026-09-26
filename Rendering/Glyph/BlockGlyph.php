<?php

namespace SPTK\Rendering\Glyph;

/** Rasterizes Unicode block elements and quadrant combinations. */
final class BlockGlyph {

  /** Draw one shaded, fractional, or quadrant block glyph. */
  public static function draw(GlyphMask $mask, int $code): void {
    $width = $mask->width();
    $height = $mask->height();
    if ($code >= 0x2581 && $code <= 0x2588) {
      $size = (int)round($height * ($code - 0x2580) / 8);
      $mask->rect(0, $height - $size, $width, $size);
    } else if ($code >= 0x2589 && $code <= 0x258f) {
      $size = (int)round($width * (0x2590 - $code) / 8);
      $mask->rect(0, 0, $size, $height);
    } else if ($code === 0x2580) {
      $mask->rect(0, 0, $width, $height - (int)round($height / 2));
    } else if ($code === 0x2590) {
      $middle = (int)round($width / 2);
      $mask->rect($middle, 0, $width - $middle, $height);
    } else if ($code === 0x2594) {
      $mask->rect(0, 0, $width, max(1, (int)round($height / 8)));
    } else if ($code === 0x2595) {
      $size = max(1, (int)round($width / 8));
      $mask->rect($width - $size, 0, $size, $height);
    } else if ($code >= 0x2591 && $code <= 0x2593) {
      self::shade($mask, $code - 0x2590);
    } else {
      self::quadrants($mask, $code - 0x2596);
    }
  }

  /** Draw one of three checker densities used for light, medium, and dark shade. */
  private static function shade(GlyphMask $mask, int $threshold): void {
    $pattern = [[0, 2], [3, 1]];
    for ($y = 0; $y < $mask->height(); $y++) {
      for ($x = 0; $x < $mask->width(); $x++) {
        if ($pattern[$y % 2][$x % 2] < $threshold) {
          $mask->point($x, $y);
        }
      }
    }
  }

  /** Fill the quadrants selected by a Unicode quadrant bit pattern. */
  private static function quadrants(GlyphMask $mask, int $index): void {
    $patterns = [4, 8, 1, 13, 9, 7, 11, 2, 6, 14];
    $bits = $patterns[$index];
    $middleX = (int)round($mask->width() / 2);
    $middleY = $mask->height() - (int)round($mask->height() / 2);
    for ($y = 0; $y < $mask->height(); $y++) {
      for ($x = 0; $x < $mask->width(); $x++) {
        $quadrant = ($y >= $middleY ? 2 : 0) + ($x >= $middleX ? 1 : 0);
        if ($bits & (1 << $quadrant)) {
          $mask->point($x, $y);
        }
      }
    }
  }

}
