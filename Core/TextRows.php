<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;

/** Maps logical text lines to clipped visual rows and cursor coordinates. */
final class TextRows {

  private array $cache = [];
  private string $cacheKey = '';

  /**
   * Build visual rows using no, display-word, editable-word, or character wrapping.
   * Rows are cached per line text, so after an edit only the changed lines are wrapped again.
   */
  public function build(array $lines, int $width, string $wrap, int $tabSize = 8, bool $caretRow = false): array {
    $width = max(1, $width);
    $key = "{$width}:{$wrap}:{$tabSize}:" . (int)$caretRow;
    $cache = $key === $this->cacheKey ? $this->cache : [];
    $used = [];
    $rows = [];
    foreach ($lines as $lineNumber => $line) {
      $segments = $used[$line] ??= $cache[$line] ?? match ($wrap) {
        'word' => $this->wordWrap($line, $width, $tabSize),
        'word-preserve' => $this->editableWordWrap($line, $width, $tabSize, $caretRow),
        'character' => $this->characterWrap($line, $width, $tabSize, $caretRow),
        default => [['start' => 0, 'length' => TextMetrics::length($line), 'text' => $line]],
      };
      foreach ($segments as $segment) {
        $segment['line'] = $lineNumber;
        $rows[] = $segment;
      }
    }
    $this->cache = $used;
    $this->cacheKey = $key;
    return $rows;
  }

  /** Wrap before whole words while retaining every source grapheme for editing. */
  private function editableWordWrap(string $line, int $width, int $tabSize, bool $caretRow): array {
    $glyphs = TextMetrics::glyphs($line);
    $count = count($glyphs);
    $bytes = $this->byteOffsets($glyphs);
    $spaces = array_map($this->isSpace(...), $glyphs);
    $rows = [];
    $start = 0;
    $used = 0;
    for ($index = 0; $index < $count; $index++) {
      $glyph = $glyphs[$index];
      if (!$spaces[$index] && ($index === 0 || $spaces[$index - 1]) && $used > 0) {
        $wordWidth = 0;
        for ($end = $index; $end < $count && !$spaces[$end]; $end++) {
          $wordWidth += TextMetrics::glyphWidthAt($glyphs[$end], $wordWidth, $tabSize);
        }
        if ($used + $wordWidth > $width) {
          $rows[] = $this->row($line, $bytes, $start, $index);
          $start = $index;
          $used = 0;
        }
      }
      $glyphWidth = TextMetrics::glyphWidthAt($glyph, $used, $tabSize);
      if ($used > 0 && $used + $glyphWidth > $width) {
        $rows[] = $this->row($line, $bytes, $start, $index);
        $start = $index;
        $used = 0;
        $glyphWidth = TextMetrics::glyphWidthAt($glyph, 0, $tabSize);
      }
      $used += min($width, $glyphWidth);
    }
    $rows[] = $this->row($line, $bytes, $start, $count);
    if ($caretRow && $used >= $width && $count > 0) {
      $rows[] = ['start' => $count, 'length' => 0, 'text' => ''];
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
    $glyphs = TextMetrics::glyphs($line);
    $rows = $this->characterRows($line, $glyphs, $this->byteOffsets($glyphs), 0, count($glyphs), $width, $tabSize, $used);
    if ($caretRow && $used >= $width) {
      $rows[] = ['start' => count($glyphs), 'length' => 0, 'text' => ''];
    }
    return $rows;
  }

  /** Split a grapheme range at display-cell boundaries; $used gets the last row's width. */
  private function characterRows(string $line, array $glyphs, array $bytes, int $from, int $to, int $width, int $tabSize, ?int &$used): array {
    $rows = [];
    $start = $from;
    $used = 0;
    for ($index = $from; $index < $to; $index++) {
      $glyphWidth = TextMetrics::glyphWidthAt($glyphs[$index], $used, $tabSize);
      if ($used > 0 && $used + $glyphWidth > $width) {
        $rows[] = $this->row($line, $bytes, $start, $index);
        $start = $index;
        $used = 0;
        $glyphWidth = TextMetrics::glyphWidthAt($glyphs[$index], 0, $tabSize);
      }
      $used += min($width, $glyphWidth);
    }
    $rows[] = $this->row($line, $bytes, $start, $to);
    return $rows;
  }

  /** Wrap words while retaining their original grapheme positions. */
  private function wordWrap(string $line, int $width, int $tabSize): array {
    $glyphs = TextMetrics::glyphs($line);
    $count = count($glyphs);
    $bytes = $this->byteOffsets($glyphs);
    $rows = [];
    $start = null;
    $end = 0;
    $used = 0;
    for ($index = 0; $index < $count;) {
      if ($this->isSpace($glyphs[$index])) {
        $index++;
        continue;
      }
      $wordStart = $index;
      $wordWidth = 0;
      for (; $index < $count && !$this->isSpace($glyphs[$index]); $index++) {
        $wordWidth += TextMetrics::glyphWidthAt($glyphs[$index], $wordWidth, $tabSize);
      }
      if ($wordWidth > $width) {
        if ($start !== null) {
          $rows[] = $this->row($line, $bytes, $start, $end);
          $start = null;
        }
        array_push($rows, ...$this->characterRows($line, $glyphs, $bytes, $wordStart, $index, $width, $tabSize, $unused));
        continue;
      }
      if ($start !== null) {
        // The separator keeps its tabs, so measure it from the current row width.
        $joined = $used;
        for ($glyph = $end; $glyph < $index; $glyph++) {
          $joined += TextMetrics::glyphWidthAt($glyphs[$glyph], $joined, $tabSize);
        }
        if ($joined <= $width) {
          $end = $index;
          $used = $joined;
          continue;
        }
        $rows[] = $this->row($line, $bytes, $start, $end);
      }
      $start = $wordStart;
      $end = $index;
      $used = $wordWidth;
    }
    if ($start !== null) {
      $rows[] = $this->row($line, $bytes, $start, $end);
    }
    return $rows === [] ? [['start' => 0, 'length' => 0, 'text' => '']] : $rows;
  }

  /** Return the byte offset of every grapheme, plus the total length at the end. */
  private function byteOffsets(array $glyphs): array {
    $offsets = [0];
    $offset = 0;
    foreach ($glyphs as $glyph) {
      $offsets[] = $offset += strlen($glyph);
    }
    return $offsets;
  }

  /** Cut a grapheme range from the line by byte offsets. */
  private function row(string $line, array $bytes, int $start, int $end): array {
    return ['start' => $start, 'length' => $end - $start, 'text' => substr($line, $bytes[$start], $bytes[$end] - $bytes[$start])];
  }

  private function isSpace(string $glyph): bool {
    return strlen($glyph) === 1 ? $glyph === ' ' || $glyph === "\t" : preg_match('/^\s+$/u', $glyph) === 1;
  }

}
