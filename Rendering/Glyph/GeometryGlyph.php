<?php

namespace SPTK\Rendering\Glyph;

use SPTK\Core\Color;
use SPTK\Layout\Tile;
use SPTK\Rendering\PixelRenderer;

/** Paints font-independent masks for terminal geometry and symbol glyphs. */
final class GeometryGlyph {

  private static array $cache = [];

  /** Report whether a glyph has a built-in geometric rendering. */
  public static function supports(string $glyph): bool {
    if (mb_strlen($glyph) !== 1) {
      return false;
    }
    $code = mb_ord($glyph);
    return ($code >= 0x2500 && $code <= 0x25ff) || ($code >= 0x2800 && $code <= 0x28ff);
  }

  /** Draw a supported glyph as cached pixel spans, independent of font coverage. */
  public static function draw(string $glyph, Tile $area, Color $color, PixelRenderer $renderer): bool {
    if (!self::supports($glyph)) {
      return false;
    }
    if ($area->width < 1 || $area->height < 1) {
      return true;
    }
    $key = $glyph . ':' . $area->width . 'x' . $area->height;
    $spans = self::$cache[$key] ?? self::createSpans($glyph, $area->width, $area->height);
    self::$cache[$key] = $spans;
    foreach ($spans as $y => $row) {
      foreach ($row as [$x, $width]) {
        $renderer->fill(new Tile($area->x + $x, $area->y + $y, $width, 1), $color);
      }
    }
    return true;
  }

  /** Build the pixel mask for one Unicode geometry glyph. */
  private static function createSpans(string $glyph, int $width, int $height): array {
    $mask = new GlyphMask($width, $height);
    $code = mb_ord($glyph);
    if ($code < 0x2580) {
      BoxGlyph::draw($mask, $glyph, $code);
    } else if ($code < 0x25a0) {
      BlockGlyph::draw($mask, $code);
    } else if ($code < 0x2600) {
      ShapeGlyph::draw($mask, $code);
    } else {
      BrailleGlyph::draw($mask, $code - 0x2800);
    }
    return $mask->spans();
  }

}
