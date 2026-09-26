<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Color, ScrollIndicator, TextCursor};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints shared read-only and editable text rows, selection, cursor, and indicators. */
final class Painter {

  /** Create a painter for one widget's inherited colors. */
  public function __construct(
    private readonly Color $fg,
    private readonly Color $bg,
    private readonly Color $cursorFg,
    private readonly Color $cursorBg,
    private readonly Color $indicatorFg,
  ) {
  }

  /** Paint visible rows, optional selection and cursor, and scroll indicators. */
  public function paint(GridWriter $writer, array $rows, array $lines, TextCursor $cursor, int $scrollY, int $scrollX, bool $wrap, array $caret, int $tabSize = 8, bool $showSelection = true): void {
    $writer->fill($this->fg, $this->bg);
    for ($y = 0; $y < $writer->height(); $y++) {
      $row = $rows[$scrollY + $y] ?? null;
      if ($row !== null) {
        $this->paintRow($writer, $row, $lines, $cursor, $y, $scrollX, $tabSize, $showSelection);
      }
    }
    $this->paintIndicators($writer, $rows, $scrollY, $scrollX, $wrap, $tabSize);
    if ($caret[0] >= 0) {
      $this->paintCursor($writer, $lines, $cursor, $caret, $scrollY, $scrollX, $tabSize);
    }
  }

  /** Draw a visual row and tint its selected source graphemes. */
  private function paintRow(GridWriter $writer, array $row, array $lines, TextCursor $cursor, int $y, int $scrollX, int $tabSize, bool $showSelection): void {
    $range = $cursor->hasSelection() && $showSelection ? $cursor->selectionRange() : null;
    foreach (TextMetrics::cells($row['text'], $scrollX, $writer->width(), $tabSize) as $x => [$glyph, $span, $index]) {
      if ($span === 0) {
        continue;
      }
      $column = $row['start'] + $index;
      $selected = $range !== null && $index < $row['length'] && $this->within($row['line'], $column, $range);
      $writer->set($x, $y, $glyph, $selected ? $this->cursorFg : $this->fg, $selected ? $this->cursorBg : $this->bg);
    }
    $line = $lines[$row['line']];
    $end = $row['start'] + $row['length'];
    if ($range !== null && $row['line'] < count($lines) - 1 && $end === TextMetrics::length($line)
      && $this->within($row['line'], $end, $range)) {
      $x = TextMetrics::width($row['text'], $tabSize) - $scrollX;
      if ($x >= 0 && $x < $writer->width()) {
        $writer->set($x, $y, '¶', $this->cursorFg, $this->cursorBg);
      }
    }
  }

  /** Check whether a document position is in a selected inclusive range. */
  private function within(int $row, int $column, array $range): bool {
    return [$row, $column] >= [$range[0], $range[1]] && [$row, $column] < [$range[2], $range[3]];
  }

  /** Overlay marks for text hidden beyond the current viewport. */
  private function paintIndicators(GridWriter $writer, array $rows, int $scrollY, int $scrollX, bool $wrap, int $tabSize): void {
    $height = $writer->height();
    $width = $writer->width();
    $above = ScrollIndicator::label($scrollY, $height, '▲');
    $below = ScrollIndicator::label(max(0, count($rows) - $scrollY - $height), $height, '▼');
    $left = $wrap ? '' : ScrollIndicator::label($scrollX, $width, '◀');
    $contentWidth = 0;
    foreach ($rows as $row) {
      $contentWidth = max($contentWidth, TextMetrics::width($row['text'], $tabSize));
    }
    $right = $wrap ? '' : ScrollIndicator::label(max(0, $contentWidth - $scrollX - $width), $width, '▶');
    $this->indicator($writer, $above, 0, true);
    $this->indicator($writer, $left, $height - 1, false);
    $belowRow = $right === '' ? $height - 1 : $height - 2;
    if ($belowRow >= 0) {
      $this->indicator($writer, $below, $belowRow, true);
    }
    if ($right !== '') {
      $this->indicator($writer, $right, $height - 1, true);
    }
  }

  /** Draw one compact indicator without crossing the tile boundary. */
  private function indicator(GridWriter $writer, string $label, int $y, bool $right, ?int $x = null): void {
    if ($label === '') {
      return;
    }
    if (TextMetrics::width($label) > $writer->width()) {
      $label = preg_replace('/[0-9]/', '', $label);
    }
    $label = TextMetrics::slice($label, 0, $writer->width());
    $writer->write($x ?? ($right ? $writer->width() - TextMetrics::width($label) : 0), $y, $label, $this->bg, $this->indicatorFg);
  }

  /** Paint the block cursor after indicators so it remains visible at corners. */
  private function paintCursor(GridWriter $writer, array $lines, TextCursor $cursor, array $caret, int $scrollY, int $scrollX, int $tabSize): void {
    [$visual, $column] = $caret;
    $y = $visual - $scrollY;
    $x = $column - $scrollX;
    if ($y < 0 || $y >= $writer->height() || $x < 0 || $x >= $writer->width()) {
      return;
    }
    [$line, $index] = $cursor->position();
    $glyph = $line < count($lines) - 1 && $index === TextMetrics::length($lines[$line])
      ? '¶' : TextMetrics::slice($lines[$line], $index, 1);
    if ($glyph !== "\t" && preg_match('/^[\p{Cc}\p{Cf}]/u', $glyph)) {
      $glyph = '�';
    }
    if ($glyph === '' || $glyph === "\t" || TextMetrics::glyphWidthAt($glyph, $column, $tabSize) + $x > $writer->width()) {
      $glyph = ' ';
    }
    $writer->set($x, $y, $glyph, $this->cursorFg, $this->cursorBg);
  }

}
