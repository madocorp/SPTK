<?php

namespace SPTK\Widgets\Table;

use SPTK\Core\{Cell, Color, ScrollIndicator, Style};
use SPTK\Layout\Tile;
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
    $this->paintHeader($writer, $data->header(), $widths, $columnScroll, $numberWidth);
    for ($y = 1; $y < $writer->height(); $y++) {
      $index = $rowScroll + $y - 1;
      $row = $data->row($index);
      if ($row === false) {
        break;
      }
      $cursor = $active && $index === $cursorRow ? $cursorColumn : null;
      $this->paintRow($writer, $y, $row, $widths, $columnScroll, $index, $cursor, $numberWidth, $selection);
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
      $cursor = $active && $index === $cursorRow ? $cursorColumn : null;
      $this->paintRow($writer, $y, $values, $widths, $columnScroll, $index, $cursor, $numberWidth, $selection);
    }
    $this->indicators($writer, $data, $widths, $rowScroll, $columnScroll, $numberWidth);
  }

  /** Paint the fixed header with inverted colors and an optional number heading. */
  private function paintHeader(GridWriter $writer, array $fields, array $widths, int $columnScroll, int $numberWidth): void {
    $writer->fillRow(0, $this->style->background, $this->style->foreground);
    if ($numberWidth > 0) {
      $this->paintRowNumber($writer, 0, $numberWidth, '#');
    }
    $x = $numberWidth - $columnScroll;
    foreach ($widths as $column => $width) {
      if ($x >= $writer->width()) {
        break;
      }
      if ($x + $width > $numberWidth) {
        $this->paintHeaderCell($writer, new Tile($x, 0, $width, 1), $fields[$column] ?? null, $numberWidth);
      }
      $x += $width;
    }
  }

  /** Paint a body row with its fixed number column and selected fields. */
  private function paintRow(GridWriter $writer, int $y, array $fields, array $widths, int $columnScroll, int $rowIndex, ?int $cursorColumn, int $numberWidth, Selection $selection): void {
    if ($numberWidth > 0) {
      $this->paintRowNumber($writer, $y, $numberWidth, (string)($rowIndex + 1));
    }
    $x = $numberWidth - $columnScroll;
    foreach ($widths as $column => $width) {
      if ($x >= $writer->width()) {
        break;
      }
      if ($x + $width > $numberWidth) {
        $selected = $column === $cursorColumn || $selection->includes($rowIndex, $column);
        $this->paintBodyCell($writer, new Tile($x, $y, $width, 1), $fields[$column] ?? null, $selected, $numberWidth);
      }
      $x += $width;
    }
  }

  /** Paint an inverted heading without cursor or selection decoration. */
  private function paintHeaderCell(GridWriter $writer, Tile $area, ?string $value, int $clipLeft): void {
    $fg = $this->style->background;
    $bg = $this->style->foreground;
    $this->paintText($writer, $area, $value, $fg, $bg, $clipLeft);
    $this->paintSeparator($writer, $area, $fg, $bg, $clipLeft);
  }

  /** Paint a body field while keeping its separator outside the selection colors. */
  private function paintBodyCell(GridWriter $writer, Tile $area, ?string $value, bool $selected, int $clipLeft): void {
    $fg = $selected ? $this->style->cursorForeground : $this->style->foreground;
    $bg = $selected ? $this->style->cursorBackground : $this->style->background;
    if ($selected) {
      $this->fillCell($writer, $area, $fg, $bg, $clipLeft);
    }
    $this->paintText($writer, $area, $value, $fg, $bg, $clipLeft);
    $this->paintSeparator($writer, $area, $this->style->foreground, $this->style->background, $clipLeft);
  }

  /** Paint a right-aligned row number or number heading in the fixed gutter. */
  private function paintRowNumber(GridWriter $writer, int $y, int $width, string $label): void {
    $fg = $this->style->background;
    $bg = $this->style->foreground;
    $area = new Tile(0, $y, $width, 1);
    $this->fillCell($writer, $area, $fg, $bg, 0);
    [$text, $suffix] = $this->clip($label, max(0, $width - 2), '');
    $x = max(0, $width - 1 - TextMetrics::width($text . $suffix));
    $writer->write($x, $y, $text, $fg, $bg);
    $writer->write($x + TextMetrics::width($text), $y, $suffix, $this->style->highlight, $bg);
    $this->paintSeparator($writer, $area, $fg, $bg, 0);
  }

  /** Fill visible cell padding without entering the fixed number column. */
  private function fillCell(GridWriter $writer, Tile $area, Color $fg, Color $bg, int $clipLeft): void {
    $blank = new Cell(' ', $fg, $bg);
    $right = min($writer->width(), $area->x + $area->width);
    for ($x = max($clipLeft, $area->x); $x < $right; $x++) {
      $writer->put($x, $area->y, $blank);
    }
  }

  /** Paint clipped field text with highlighted null, truncation, and multiline markers. */
  private function paintText(GridWriter $writer, Tile $area, ?string $value, Color $fg, Color $bg, int $clipLeft): void {
    [$text, $multiline] = $this->display($value);
    [$text, $suffix] = $this->clip($text, max(0, $area->width - 2), $multiline ? 'V' : '');
    $left = max($clipLeft, $area->x);
    $right = min($writer->width(), $area->x + $area->width);
    $position = $area->x + 1;
    foreach ([[$text, $value === null ? $this->style->highlight : $fg], [$suffix, $this->style->highlight]] as [$run, $color]) {
      foreach (TextMetrics::glyphs($run) as $glyph) {
        $glyphWidth = TextMetrics::glyphWidth($glyph);
        if ($position >= $left && $position + $glyphWidth <= $right) {
          $writer->put($position, $area->y, new Cell($glyph, $color, $bg, $glyphWidth));
        }
        $position += $glyphWidth;
      }
    }
  }

  /** Draw a column boundary only when it is visible beyond the fixed gutter. */
  private function paintSeparator(GridWriter $writer, Tile $area, Color $fg, Color $bg, int $clipLeft): void {
    $x = $area->x + $area->width - 1;
    if ($x >= max($clipLeft, $area->x) && $x < $writer->width()) {
      $writer->put($x, $area->y, new Cell('│', $fg, $bg));
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
