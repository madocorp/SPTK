<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;

/** Maps logical text lines to clipped visual rows and cursor coordinates. */
final class TextRows {

  /** Build visual rows using no wrapping, word wrapping, or character wrapping. */
  public function build(array $lines, int $width, string $wrap, int $tabSize = 8, bool $caretRow = false): array {
    $rows = [];
    foreach ($lines as $lineNumber => $line) {
      $segments = match ($wrap) {
        'word' => $this->wordWrap($line, max(1, $width), $tabSize),
        'character' => $this->characterWrap($line, max(1, $width), $tabSize, $caretRow),
        default => [['start' => 0, 'length' => TextMetrics::length($line), 'text' => $line]],
      };
      foreach ($segments as $segment) {
        $segment['line'] = $lineNumber;
        $rows[] = $segment;
      }
    }
    return $rows;
  }

  /** Find the visual row and display column occupied by a logical cursor. */
  public function cursor(array $rows, TextCursor $cursor, int $tabSize = 8): array {
    [$line, $column] = $cursor->position();
    $visualRow = 0;
    $cellColumn = 0;
    foreach ($rows as $index => $row) {
      if ($row['line'] !== $line || $column < $row['start']) {
        continue;
      }
      $visualRow = $index;
      $offset = min($row['length'], max(0, $column - $row['start']));
      $cellColumn = TextMetrics::width(TextMetrics::slice($row['text'], 0, $offset), $tabSize);
    }
    return [$visualRow, $cellColumn];
  }

  /** Split one line at display-cell boundaries. */
  private function characterWrap(string $line, int $width, int $tabSize, bool $caretRow): array {
    $rows = [];
    $start = 0;
    $length = 0;
    $used = 0;
    foreach (TextMetrics::glyphs($line) as $index => $glyph) {
      $glyphWidth = TextMetrics::glyphWidthAt($glyph, $used, $tabSize);
      if ($used > 0 && $used + $glyphWidth > $width) {
        $rows[] = ['start' => $start, 'length' => $length, 'text' => TextMetrics::slice($line, $start, $length)];
        $start = $index;
        $length = 0;
        $used = 0;
        $glyphWidth = TextMetrics::glyphWidthAt($glyph, 0, $tabSize);
      }
      $length++;
      $used += min($width, $glyphWidth);
    }
    $rows[] = ['start' => $start, 'length' => $length, 'text' => TextMetrics::slice($line, $start, $length)];
    if ($caretRow && $used >= $width) {
      $rows[] = ['start' => TextMetrics::length($line), 'length' => 0, 'text' => ''];
    }
    return $rows;
  }

  /** Wrap words while retaining their original grapheme positions. */
  private function wordWrap(string $line, int $width, int $tabSize): array {
    preg_match_all('/\S+/u', $line, $matches, PREG_OFFSET_CAPTURE);
    if ($matches[0] === []) {
      return [['start' => 0, 'length' => 0, 'text' => '']];
    }
    $rows = [];
    $current = null;
    $previousEnd = 0;
    foreach ($matches[0] as [$word, $byteOffset]) {
      $wordStart = TextMetrics::length(substr($line, 0, $byteOffset));
      $wordLength = TextMetrics::length($word);
      $separator = $current === null ? '' : TextMetrics::slice($line, $previousEnd, $wordStart - $previousEnd);
      if (TextMetrics::width($word, $tabSize) > $width) {
        if ($current !== null) {
          $rows[] = $current;
          $current = null;
        }
        foreach ($this->characterWrap($word, $width, $tabSize, false) as $part) {
          $part['start'] += $wordStart;
          $rows[] = $part;
        }
      } else if ($current === null) {
        $current = ['start' => $wordStart, 'length' => $wordLength, 'text' => $word];
      } else if (TextMetrics::width($current['text'] . $separator . $word, $tabSize) <= $width) {
        $current['length'] = $wordStart + $wordLength - $current['start'];
        $current['text'] .= $separator . $word;
      } else {
        $rows[] = $current;
        $current = ['start' => $wordStart, 'length' => $wordLength, 'text' => $word];
      }
      $previousEnd = $wordStart + $wordLength;
    }
    if ($current !== null) {
      $rows[] = $current;
    }
    return $rows;
  }

}
