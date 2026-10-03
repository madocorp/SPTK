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

  public static function width(string $text, int $tabSize = 8): int {
    return self::widthWithTabs($text, $tabSize);
  }

  /** Measure display columns with configurable tab stops. */
  public static function widthWithTabs(string $text, int $tabSize): int {
    $width = 0;
    foreach (self::glyphs($text) as $glyph) {
      $width += self::glyphWidthAt($glyph, $width, $tabSize);
    }
    return $width;
  }

  /** Measure a glyph at its current display column. */
  public static function glyphWidthAt(string $glyph, int $column, int $tabSize = 8): int {
    return $glyph === "\t" ? $tabSize - $column % $tabSize : self::glyphWidth($glyph);
  }

  /** Return the display column before a grapheme index. */
  public static function column(string $text, int $index, int $tabSize = 8): int {
    return self::widthWithTabs(self::slice($text, 0, $index), $tabSize);
  }

  /** Return the grapheme index at or before a display column. */
  public static function index(string $text, int $column, int $tabSize = 8): int {
    $position = 0;
    foreach (self::glyphs($text) as $index => $glyph) {
      $next = $position + self::glyphWidthAt($glyph, $position, $tabSize);
      if ($next > $column) {
        return $index;
      }
      $position = $next;
    }
    return self::length($text);
  }

  /** Build visible cells with source grapheme indices and blank tab cells. */
  public static function cells(string $text, int $offset, int $columns, int $tabSize = 8): array {
    $glyphs = self::glyphs($text);
    $cells = array_fill(0, max(0, $columns), [' ', 1, count($glyphs)]);
    $position = 0;
    foreach ($glyphs as $index => $glyph) {
      $width = self::glyphWidthAt($glyph, $position, $tabSize);
      $x = $position - $offset;
      if ($x >= $columns) {
        break;
      }
      if ($x + $width > 0) {
        if ($glyph === "\t" || $x < 0 || $x + $width > $columns) {
          for ($part = max(0, $x); $part < min($columns, $x + $width); $part++) {
            $cells[$part] = [' ', 1, $index];
          }
        } else {
          $display = preg_match('/^[\p{Cc}\p{Cf}]/u', $glyph) ? '�' : $glyph;
          $cells[$x] = [$display, $width, $index];
          if ($width === 2) {
            $cells[$x + 1] = ['', 0, $index];
          }
        }
      }
      $position += $width;
    }
    return $cells;
  }

}
