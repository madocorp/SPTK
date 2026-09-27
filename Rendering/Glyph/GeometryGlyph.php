<?php

namespace SPTK\Rendering\Glyph;

/** Builds font-independent masks for terminal geometry and symbol glyphs. */
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

  /** Return cached pixel spans for a supported glyph at one cell size. */
  public static function spans(string $glyph, int $width, int $height): array {
    $key = $glyph . ':' . $width . 'x' . $height;
    return self::$cache[$key] ??= self::createSpans($glyph, $width, $height);
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
