<?php

namespace SPTK\Rendering\Glyph;

/** Rasterizes Braille pattern dots into font-sized pixel masks. */
final class BrailleGlyph {

  /** Draw the eight dots encoded by one Braille codepoint. */
  public static function draw(GlyphMask $mask, int $bits): void {
    $dots = [[0, 0], [0, 1], [0, 2], [1, 0], [1, 1], [1, 2], [0, 3], [1, 3]];
    foreach ($dots as $bit => [$column, $row]) {
      if (($bits & (1 << $bit)) === 0) {
        continue;
      }
      $width = max(1, (int)round($mask->width() / 4));
      $height = max(1, min($width, (int)round($mask->height() / 8)));
      $x = (int)floor(($column + 0.5) * $mask->width() / 2 - $width / 2);
      $y = (int)floor(($row + 0.5) * $mask->height() / 4 - $height / 2);
      $mask->rect(max(0, $x), max(0, $y), $width, $height);
    }
  }

}
