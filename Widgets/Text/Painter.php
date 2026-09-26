<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Color, ScrollIndicator, TextCursor};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints Text rows, selection, cursor, and scroll indicators. */
final class Painter {

  public function __construct(
    private readonly Color $fg,
    private readonly Color $bg,
    private readonly Color $cursorFg,
    private readonly Color $cursorBg,
    private readonly Color $indicatorFg,
  ) {
  }

  /** Draw visible text, selection, indicators, and the optional caret. */
  public function paint(GridWriter $writer, array $rows, TextCursor $cursor, int $scrollY, int $scrollX, bool $wrap, array $caret): void {
    for ($y = 0; $y < $writer->height(); $y++) {
      $row = $rows[$scrollY + $y] ?? null;
      if ($row === null) {
        continue;
      }
      $writer->write(0, $y, $wrap ? $row['text'] : $this->visibleText($row['text'], $scrollX), $this->fg, $this->bg);
      $this->paintSelection($writer, $row, $y, $cursor, $scrollX);
    }
    $this->paintIndicators($writer, count($rows), $scrollY, $scrollX, $wrap, $rows);
    if ($caret[0] >= 0) {
      $this->paintCursor($writer, $caret, $rows, $scrollY, $cursor);
    }
  }

  /** Return a line's visible suffix after horizontal scrolling. */
  private function visibleText(string $text, int $scrollX): string {
    $result = '';
    $column = 0;
    foreach (TextMetrics::glyphs($text) as $glyph) {
      $width = TextMetrics::width($glyph);
      if ($column + $width <= $scrollX) {
        $column += $width;
        continue;
      }
      $result .= $column < $scrollX ? str_repeat(' ', $column + $width - $scrollX) : $glyph;
      $column += $width;
    }
    return $result;
  }

  /** Highlight selected graphemes in one visible visual row. */
  private function paintSelection(GridWriter $writer, array $row, int $y, TextCursor $cursor, int $scrollX): void {
    if (!$cursor->hasSelection()) {
      return;
    }
    [$startRow, $startColumn, $endRow, $endColumn] = $cursor->selectionRange();
    if ($row['line'] < $startRow || $row['line'] > $endRow) {
      return;
    }
    $start = max($row['start'], $row['line'] === $startRow ? $startColumn : 0);
    $end = min($row['start'] + $row['length'], $row['line'] === $endRow ? $endColumn : PHP_INT_MAX);
    for ($column = $start; $column < $end; $column++) {
      $glyph = TextMetrics::slice($row['text'], $column - $row['start'], 1);
      $x = TextMetrics::width(TextMetrics::slice($row['text'], 0, $column - $row['start'])) - $scrollX;
      if ($x >= 0 && $x < $writer->width()) {
        $writer->set($x, $y, $glyph, $this->cursorFg, $this->cursorBg);
      }
    }
  }

  /** Paint vertical and horizontal marks for content outside the viewport. */
  private function paintIndicators(GridWriter $writer, int $rowCount, int $scrollY, int $scrollX, bool $wrap, array $rows): void {
    $above = ScrollIndicator::label($scrollY, $writer->height(), '▲');
    $below = ScrollIndicator::label(max(0, $rowCount - $scrollY - $writer->height()), $writer->height(), '▼');
    if ($above !== '') {
      $writer->write(max(0, $writer->width() - TextMetrics::width($above)), 0, $above, $this->indicatorFg, $this->bg);
    }
    $left = $wrap ? '' : ScrollIndicator::label($scrollX, $writer->width(), '◀');
    $contentWidth = max(array_map(static fn(array $row): int => TextMetrics::width($row['text']), $rows));
    $right = $wrap ? '' : ScrollIndicator::label(max(0, $contentWidth - $scrollX - $writer->width()), $writer->width(), '▶');
    if ($left !== '') {
      $writer->write(0, $writer->height() - 1, $left, $this->indicatorFg, $this->bg);
    }
    if ($below !== '') {
      $writer->write(max(0, $writer->width() - TextMetrics::width($below)), $writer->height() - 1, $below, $this->indicatorFg, $this->bg);
    }
    if ($right !== '') {
      $x = max(TextMetrics::width($left), $writer->width() - TextMetrics::width($below) - TextMetrics::width($right));
      $writer->write($x, $writer->height() - 1, $right, $this->indicatorFg, $this->bg);
    }
  }

  /** Paint the active caret over its visible text cell. */
  private function paintCursor(GridWriter $writer, array $caret, array $rows, int $scrollY, TextCursor $cursor): void {
    [$row, $column] = $caret;
    $y = $row - $scrollY;
    if ($y < 0 || $y >= $writer->height()) {
      return;
    }
    $rowData = $rows[$row];
    [, $textColumn] = $cursor->position();
    $glyph = TextMetrics::slice($rowData['text'], $textColumn - $rowData['start'], 1);
    if ($glyph === '' || TextMetrics::width($glyph) + $column > $writer->width()) {
      $glyph = ' ';
    }
    $writer->set($column, $y, $glyph, $this->cursorFg, $this->cursorBg);
  }

}
