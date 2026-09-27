<?php

namespace SPTK\Widgets\Table;

use SPTK\Core\{Cell, Color, ScrollIndicator, Style};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Measures columns and paints a clipped, scrollable table grid. */
final class Painter {

  /** Keep the inherited colors used by header, fields, and cursor. */
  public function __construct(private Style $style) {
  }

  /** Measure each column from its heading and data, including cell padding. */
  public function widths(TableData $data, array $specified): array {
    $widths = [];
    for ($column = 0; $column < $data->columns(); $column++) {
      $widths[$column] = $specified[$column] ?? 6;
    }
    foreach ([$data->header(), ...$data->measurementRows()] as $row) {
      foreach ($row as $column => $field) {
        if (!isset($specified[$column])) {
          [$text, $multiline] = $this->display($field);
          $widths[$column] = max($widths[$column] ?? 6, TextMetrics::width($text) + ($multiline ? 1 : 0) + 2);
        }
      }
    }
    return $widths;
  }

  /** Paint the header, visible rows, cursor, and scroll indicators. */
  public function paint(GridWriter $writer, TableData $data, array $widths, int $rowScroll, int $columnScroll, int $cursorRow, int $cursorColumn, bool $active, bool $rowNumbers, Selection $selection): void {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return;
    }
    $numberWidth = $rowNumbers ? strlen((string)max(1, $data->count())) + 2 : 0;
    $writer->fillRow(0, $this->style->background, $this->style->foreground);
    $this->row($writer, 0, $data->header(), $widths, $columnScroll, -1, $cursorRow, $cursorColumn, $active, $numberWidth, $selection);
    for ($y = 1; $y < $writer->height(); $y++) {
      $index = $rowScroll + $y - 1;
      $row = $data->row($index);
      if ($row === false) {
        break;
      }
      $this->row($writer, $y, $row, $widths, $columnScroll, $index, $cursorRow, $cursorColumn, $active, $numberWidth, $selection);
    }
    $this->indicators($writer, $data, $widths, $rowScroll, $columnScroll, $numberWidth);
  }

  /** Repaint only rows touched by a cursor move in the current viewport. */
  public function paintRows(GridWriter $writer, TableData $data, array $widths, int $rowScroll, int $columnScroll, int $cursorRow, int $cursorColumn, bool $active, bool $rowNumbers, Selection $selection, array $rows): void {
    $numberWidth = $rowNumbers ? strlen((string)max(1, $data->count())) + 2 : 0;
    foreach ($rows as $index) {
      $y = $index - $rowScroll + 1;
      if ($y < 1 || $y >= $writer->height()) {
        continue;
      }
      $values = $data->row($index);
      if ($values === false) {
        continue;
      }
      $writer->fillRow($y, $this->style->foreground, $this->style->background);
      $this->row($writer, $y, $values, $widths, $columnScroll, $index, $cursorRow, $cursorColumn, $active, $numberWidth, $selection);
    }
    $this->indicators($writer, $data, $widths, $rowScroll, $columnScroll, $numberWidth);
  }

  /** Draw one row with its visible cells and optional row number. */
  private function row(GridWriter $writer, int $y, array $fields, array $widths, int $columnScroll, int $rowIndex, int $cursorRow, int $cursorColumn, bool $active, int $numberWidth, Selection $selection): void {
    $header = $rowIndex < 0;
    $x = -$columnScroll;
    if ($numberWidth > 0) {
      $label = $header ? '#' : (string)($rowIndex + 1);
      $this->cell($writer, 0, $y, $numberWidth, $label, $this->style->background, $this->style->foreground, !$header, true);
      $x += $numberWidth;
    }
    foreach ($widths as $column => $width) {
      if ($x >= $writer->width()) {
        break;
      }
      if ($x + $width > $numberWidth) {
        $selected = $active && !$header && $rowIndex === $cursorRow && $column === $cursorColumn;
        $highlighted = $selection->includes($rowIndex, $column);
        $inverted = $selected || $highlighted;
        $fg = $header ? $this->style->background : ($inverted ? $this->style->cursorForeground : $this->style->foreground);
        $bg = $header ? $this->style->foreground : ($inverted ? $this->style->cursorBackground : $this->style->background);
        $value = $fields[$column] ?? null;
        [$text, $multiline] = $this->display($value);
        $this->cell($writer, $x, $y, $width, $text, $fg, $bg, $inverted, false, $numberWidth, !$header, $multiline ? 'V' : '', $value === null);
      }
      $x += $width;
    }
  }

  /** Paint one padded cell without allowing its text to enter another column. */
  private function cell(GridWriter $writer, int $x, int $y, int $width, string $value, Color $fg, Color $bg, bool $fill, bool $right = false, int $clipLeft = 0, bool $bodySeparator = false, string $marker = '', bool $nullValue = false): void {
    $visibleLeft = max($clipLeft, $x);
    $visibleRight = min($writer->width(), $x + $width);
    if ($fill) {
      $blank = new Cell(' ', $fg, $bg);
      for ($column = $visibleLeft; $column < $visibleRight; $column++) {
        $writer->put($column, $y, $blank);
      }
    }
    $textWidth = max(0, $width - 2);
    [$text, $suffix] = $this->clip($value, $textWidth, $marker);
    $position = $right ? $x + max(0, $width - 1 - TextMetrics::width($text . $suffix)) : $x + 1;
    foreach ([[$text, $nullValue ? $this->style->highlight : $fg], [$suffix, $this->style->highlight]] as [$run, $color]) {
      foreach (TextMetrics::glyphs($run) as $glyph) {
        $glyphWidth = TextMetrics::glyphWidth($glyph);
        if ($position >= $visibleLeft && $position + $glyphWidth <= $visibleRight) {
          $writer->put($position, $y, new Cell($glyph, $color, $bg, $glyphWidth));
        }
        $position += $glyphWidth;
      }
    }
    if ($x + $width - 1 >= $visibleLeft && $x + $width - 1 < $visibleRight) {
      $writer->put($x + $width - 1, $y, new Cell('│', $bodySeparator ? $this->style->foreground : $fg, $bodySeparator ? $this->style->background : $bg));
    }
  }

  /** Clip a field while preserving its truncation and multiline markers. */
  private function clip(string $value, int $width, string $marker): array {
    if ($width < 1) {
      return ['', ''];
    }
    $truncated = TextMetrics::width($value . $marker) > $width;
    if (!$truncated) {
      return [$value, $marker];
    }
    $suffix = $width > TextMetrics::width($marker) ? '~' . $marker : $marker;
    $room = max(0, $width - TextMetrics::width($suffix));
    $text = '';
    $used = 0;
    foreach (TextMetrics::glyphs($value) as $glyph) {
      $glyphWidth = TextMetrics::glyphWidth($glyph);
      if ($used + $glyphWidth > $room) {
        break;
      }
      $text .= $glyph;
      $used += $glyphWidth;
    }
    return [$text, $suffix];
  }

  /** Format null and multiline values for a single display row. */
  private function display(?string $value): array {
    if ($value === null) {
      return ['NULL', false];
    }
    $first = preg_split('/\r\n|\r|\n/', $value, 2);
    return [str_replace("\t", ' ', $first[0]), count($first) > 1];
  }

  /** Mark hidden rows and columns at the tile edges. */
  private function indicators(GridWriter $writer, TableData $data, array $widths, int $rowScroll, int $columnScroll, int $numberWidth): void {
    $bodyHeight = max(0, $writer->height() - 1);
    $above = '';
    $below = '';
    if ($bodyHeight > 0 && $data->count() > $bodyHeight) {
      $above = ScrollIndicator::label($rowScroll, $bodyHeight, '▲');
      $below = ScrollIndicator::label(max(0, $data->count() - $rowScroll - $bodyHeight), $bodyHeight, '▼');
    }
    $left = '';
    $right = '';
    if ($numberWidth + array_sum($widths) > $writer->width()) {
      $left = ScrollIndicator::label($columnScroll, $writer->width(), '◀');
      $remaining = max(0, $numberWidth + array_sum($widths) - $columnScroll - $writer->width());
      $right = ScrollIndicator::label($remaining, $writer->width(), '▶');
    }
    $this->indicator($writer, $above, 0, true);
    $this->indicator($writer, $left, $writer->height() - 1, false);
    $this->indicator($writer, ScrollIndicator::bottomRight($right, $below), $writer->height() - 1, true);
  }

  /** Draw a scroll mark against its edge. */
  private function indicator(GridWriter $writer, string $label, int $y, bool $right): void {
    $label = ScrollIndicator::fit($label, $writer->width(), $right);
    $width = TextMetrics::width($label);
    if ($width < 1) {
      return;
    }
    $writer->write($right ? $writer->width() - $width : 0, $y, $label, $this->style->background, $this->style->highlight);
  }

}
