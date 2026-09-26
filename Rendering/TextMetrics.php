<?php

namespace SPTK\Rendering;

/** Splits graphemes and measures their occupied terminal columns. */
final class TextMetrics {

  private static array $widths = [];

  public static function glyphs(string $text): array {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Text must be valid UTF-8.');
    }
    if (preg_match('/^[\x00-\x7f]*$/D', $text)) {
      return str_split($text);
    }
    $iterator = \IntlBreakIterator::createCharacterInstance('root');
    $iterator->setText($text);
    $glyphs = [];
    $start = $iterator->first();
    while (($end = $iterator->next()) !== \IntlBreakIterator::DONE) {
      $glyphs[] = substr($text, $start, $end - $start);
      $start = $end;
    }
    return $glyphs;
  }

  /** Count grapheme clusters in text. */
  public static function length(string $text): int {
    return count(self::glyphs($text));
  }

  /** Return a grapheme-based slice of text. */
  public static function slice(string $text, int $start, ?int $length = null): string {
    return implode('', array_slice(self::glyphs($text), $start, $length));
  }

  public static function glyphWidth(string $glyph): int {
    if (strlen($glyph) === 1) {
      return 1;
    }
    if (isset(self::$widths[$glyph])) {
      return self::$widths[$glyph];
    }
    $width = 1;
    foreach (mb_str_split($glyph, 1, 'UTF-8') as $character) {
      $width = max($width, mb_strwidth($character, 'UTF-8'));
      if (!str_contains($glyph, "\u{FE0E}") && \IntlChar::hasBinaryProperty(mb_ord($character), \IntlChar::getPropertyEnum('Emoji_Presentation'))) {
        $width = 2;
      }
    }
    if (str_contains($glyph, "\u{FE0F}") || str_contains($glyph, "\u{20E3}")) {
      $width = 2;
    }
    if (count(self::$widths) >= 4096) {
      self::$widths = [];
    }
    return self::$widths[$glyph] = min(2, $width);
  }

  public static function width(string $text): int {
    $width = 0;
    foreach (self::glyphs($text) as $glyph) {
      $width += self::glyphWidth($glyph);
    }
    return $width;
  }

}
