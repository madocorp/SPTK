<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\StyleSheet;
use \SPTK\Texture;
use \SPTK\Border;
use \SPTK\Scrollbar;
use \SPTK\SDLWrapper\Action;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\TTF;
use \SPTK\SDLWrapper\SDL;

class Table extends TextGrid {

  protected string|false $file = false;
  protected int $chunkSize = 10000;
  protected int $chunkStart = 0;
  protected array $chunk = [];
  protected array $header = [];
  protected int $lineCount = 0;
  protected int $rowCount = 0;
  protected array $lineOffsets = [];
  protected array $rawColumnWidths = [];
  protected array $columnWidths = [];
  protected int $rowHeight = 18;
  protected int $cellHorizontalChrome = 0;
  protected int $cellVerticalChrome = 0;
  protected int $minFieldWidth = 40;
  protected bool $widthsMeasured = false;
  protected int $cursorRow = 0;
  protected int $cursorColumn = 0;
  protected int $anchorRow = 0;
  protected int $anchorColumn = 0;
  protected bool $active = false;
  protected array $tableStyleCache = [];

  protected function init(): void {
    if ($this->renderer !== false && TTF::$instance !== null && SDL::$instance !== null) {
      parent::init();
    } else {
      $fontSize = (int)$this->style->get('fontSize', $this->geometry);
      $this->letterWidth = max(1, (int)round($fontSize * 0.6));
      $this->letterHeight = max(1, $fontSize);
      $this->lineHeight = max(1, (int)$this->style->get('lineHeight', $this->geometry));
      $this->lineOffset = 0;
    }
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function recalculateStyle(): void {
    $this->tableStyleCache = [];
    StyleSheet::clearCache();
    parent::recalculateStyle();
  }

  public function addVariant(string $class): void {
    if ($class === 'active') {
      $this->active = true;
    }
    parent::addVariant($class);
  }

  public function removeVariant(string $class): void {
    if ($class === 'active') {
      $this->active = false;
    }
    parent::removeVariant($class);
  }

  protected function isActive(): bool {
    return $this->active || $this->hasVariant('active');
  }

  public function getAttributeList(): array {
    return ['file', 'chunkSize', 'minFieldWidth'];
  }

  public function setFile($file): void {
    if ($file === false) {
      return;
    }
    if (strpos($file, '/') !== 0) {
      if (defined('APP_PATH')) {
        $file = dirname(APP_PATH) . "/{$file}";
      } else {
        $file = getcwd() . "/{$file}";
      }
    }
    if (!file_exists($file)) {
      return;
    }
    $this->file = $file;
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->cursorRow = 0;
    $this->cursorColumn = 0;
    $this->anchorRow = 0;
    $this->anchorColumn = 0;
    $this->chunk = [];
    $this->chunkStart = 0;
    $this->rawColumnWidths = [];
    $this->columnWidths = [];
    $this->widthsMeasured = false;
    $this->scanFile();
    $this->loadChunk(0);
    $this->measureColumnWidths();
    $this->changed = true;
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
    }
  }

  public function setChunkSize($value): void {
    $value = (int)$value;
    if ($value > 0) {
      $this->chunkSize = $value;
    }
  }

  public function setMinFieldWidth($value): void {
    $value = (int)$value;
    if ($value >= 0) {
      $this->minFieldWidth = $value;
      $this->widthsMeasured = false;
      $this->measureColumnWidths();
      $this->changed = true;
    }
  }

  public function getHeader(): array {
    return $this->header;
  }

  public function getLineCount(): int {
    return $this->lineCount;
  }

  public function getRowCount(): int {
    return $this->rowCount;
  }

  public function getChunkStart(): int {
    return $this->chunkStart;
  }

  public function getChunk(): array {
    return $this->chunk;
  }

  public function getColumnWidths(): array {
    return $this->columnWidths;
  }

  public function getCursor(): array {
    return [$this->cursorRow, $this->cursorColumn];
  }

  public function getActiveCellValue(): mixed {
    if ($this->rowCount === 0) {
      return false;
    }
    $this->clampCursor();
    if ($this->cursorRow < $this->chunkStart || $this->cursorRow >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($this->cursorRow);
    }
    $row = $this->chunk[$this->cursorRow - $this->chunkStart] ?? false;
    if ($row === false) {
      return false;
    }
    return $row[$this->cursorColumn] ?? null;
  }

  public function getSelection(): array {
    return [
      min($this->cursorRow, $this->anchorRow),
      min($this->cursorColumn, $this->anchorColumn),
      max($this->cursorRow, $this->anchorRow),
      max($this->cursorColumn, $this->anchorColumn),
    ];
  }

  public function scrollToRow(int $row): void {
    $row = max(0, min($row, max(0, $this->rowCount - 1)));
    $this->scrollY = $row * $this->rowHeight;
    $this->reloadVisibleChunk();
    $this->clampScroll();
    $this->changed = true;
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
    }
  }

  protected function scanFile(): void {
    $this->header = [];
    $this->lineCount = 0;
    $this->rowCount = 0;
    $this->lineOffsets = [];

    $handle = fopen($this->file, 'rb');
    if ($handle === false) {
      return;
    }
    while (($offset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
      if ($this->lineCount % $this->chunkSize === 0) {
        $this->lineOffsets[$this->lineCount] = $offset;
      }
      if ($this->lineCount === 0) {
        $this->header = $this->parseLine($line);
      }
      $this->lineCount++;
    }
    fclose($handle);
    $this->rowCount = max(0, $this->lineCount - 1);
  }

  protected function loadChunk(int $row): void {
    if ($this->file === false) {
      return;
    }
    $row = max(0, min($row, max(0, $this->rowCount - 1)));
    $this->chunkStart = (int)(floor($row / $this->chunkSize) * $this->chunkSize);
    $targetLine = $this->chunkStart + 1;
    $seekLine = $this->nearestIndexedLine($targetLine);

    $handle = fopen($this->file, 'rb');
    if ($handle === false) {
      return;
    }
    fseek($handle, $this->lineOffsets[$seekLine] ?? 0);
    for ($line = $seekLine; $line < $targetLine; $line++) {
      if (fgets($handle) === false) {
        break;
      }
    }
    $this->chunk = [];
    for ($i = 0; $i < $this->chunkSize && ($line = fgets($handle)) !== false; $i++) {
      $this->chunk[] = $this->parseLine($line);
    }
    fclose($handle);
  }

  protected function nearestIndexedLine(int $targetLine): int {
    $nearest = 0;
    foreach ($this->lineOffsets as $line => $offset) {
      if ($line > $targetLine) {
        break;
      }
      $nearest = $line;
    }
    return $nearest;
  }

  protected function parseLine(string $line): array {
    $line = rtrim($line, "\r\n");
    $fields = [];
    $field = '';
    $escaping = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
      $char = $line[$i];
      if ($escaping) {
        $field .= match ($char) {
          't' => "\t",
          'n' => "\n",
          'r' => "\r",
          '\\' => '\\',
          default => "\\{$char}",
        };
        $escaping = false;
        continue;
      }
      if ($char === "\\") {
        $escaping = true;
      } else if ($char === "\t") {
        $fields[] = ($field === '\N' ? null : $field);
        $field = '';
      } else {
        $field .= $char;
      }
    }
    if ($escaping) {
      $field .= '\\';
    }
    $fields[] = ($field === '\N' ? null : $field);
    return $fields;
  }

  protected function measureColumnWidths(): void {
    $this->syncTextMetrics();
    if (!$this->widthsMeasured) {
      $widths = [];
      foreach (array_merge([$this->header], $this->chunk) as $row) {
        foreach ($row as $i => $field) {
          $text = $field === null ? '' : (string)$field;
          $width = mb_strlen($text) * $this->letterWidth + $this->cellHorizontalChrome;
          $widths[$i] = max($widths[$i] ?? $this->minFieldWidth, $width, $this->minFieldWidth);
        }
      }
      $columns = max(count($this->header), count($widths));
      for ($i = 0; $i < $columns; $i++) {
        $widths[$i] = $widths[$i] ?? $this->minFieldWidth;
      }
      ksort($widths);
      $this->rawColumnWidths = array_values($widths);
      $this->widthsMeasured = true;
    }
    $this->columnWidths = $this->rawColumnWidths;
    $this->limitColumnWidthsToBox();
  }

  protected function limitColumnWidthsToBox(): void {
    if ($this->geometry->innerWidth <= 0) {
      return;
    }
    if (array_sum($this->rawColumnWidths) <= $this->geometry->innerWidth) {
      return;
    }
    $maxFieldWidth = max($this->minFieldWidth, (int)floor($this->geometry->innerWidth * 0.5));
    foreach ($this->columnWidths as $i => $width) {
      $this->columnWidths[$i] = min($width, $maxFieldWidth);
    }
  }

  protected function syncTextMetrics(): void {
    $fontSize = (int)$this->style->get('fontSize', $this->geometry);
    if (TTF::$instance === null || $fontSize <= 0) {
      $this->lineHeight = max(1, (int)$this->style->get('lineHeight', $this->geometry));
      $this->letterWidth = max(1, (int)round($fontSize * 0.6));
      $this->letterHeight = max(1, $fontSize);
      $this->lineOffset = 0;
      $this->syncCellMetrics();
      return;
    }
    $this->font = new Font($this->style->get('font'), $fontSize);
    $this->letterWidth = max(1, $this->font->letterWidth);
    $this->letterHeight = max(1, $this->font->letterHeight);
    $this->lineHeight = max(1, $this->font->height);
    $this->lineOffset = $this->font->height - $this->font->letterHeight;
    $this->syncCellMetrics();
  }

  protected function syncCellMetrics(): void {
    $cellStyle = $this->styleFor('TableCell');
    $headerStyle = $this->styleFor('TableHeader');
    $this->cellHorizontalChrome =
      $cellStyle->get('paddingLeft', $this->geometry) +
      $cellStyle->get('paddingRight', $this->geometry) +
      $cellStyle->get('borderLeft', $this->geometry) +
      $cellStyle->get('borderRight', $this->geometry);
    $this->cellVerticalChrome =
      $cellStyle->get('paddingTop', $this->geometry) +
      $cellStyle->get('paddingBottom', $this->geometry) +
      $cellStyle->get('borderTop', $this->geometry) +
      $cellStyle->get('borderBottom', $this->geometry);
    $headerVerticalChrome =
      $headerStyle->get('paddingTop', $this->geometry) +
      $headerStyle->get('paddingBottom', $this->geometry) +
      $headerStyle->get('borderTop', $this->geometry) +
      $headerStyle->get('borderBottom', $this->geometry);
    $this->rowHeight = $this->lineHeight + max($this->cellVerticalChrome, $headerVerticalChrome);
  }

  protected function styleFor(string $type, array $classes = []): \SPTK\Style {
    $key = $type . '|' . implode(' ', $classes);
    if (!isset($this->tableStyleCache[$key])) {
      $this->tableStyleCache[$key] = StyleSheet::get($this->style, $this->style, $type, $classes);
    }
    return $this->tableStyleCache[$key];
  }

  protected function styleValue(\SPTK\Style $style, string $name, mixed $fallback = null, ?\SPTK\Geometry $geometry = null): mixed {
    try {
      return $style->get($name, $geometry ?? $this->geometry);
    } catch (\Exception $e) {
      return $fallback;
    }
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->syncTextMetrics();
    $this->measureColumnWidths();
    $this->clampScroll();
  }

  protected function calculateHeights(): void {
    if ($this->display === false) {
      return;
    }
    $this->geometry->contentHeight =
      $this->geometry->paddingTop +
      $this->rowHeight +
      $this->rowCount * $this->rowHeight +
      $this->geometry->paddingBottom;
    if ($this->geometry->height === 'content') {
      $this->geometry->height =
        $this->geometry->borderTop +
        $this->geometry->contentHeight +
        $this->geometry->borderBottom;
      $this->geometry->limitateHeight();
      $this->geometry->setDerivedHeights();
    }
    $this->geometry->ascent = $this->geometry->fullHeight - $this->geometry->marginBottom;
    $this->geometry->descent = $this->geometry->marginBottom;
  }

  protected function layout(): void {
    if ($this->display === false) {
      return;
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
    $this->columnWidths = $this->rawColumnWidths;
    $this->limitColumnWidthsToBox();
    $this->reloadVisibleChunk();
    $this->geometry->contentWidth =
      $this->geometry->paddingLeft +
      array_sum($this->columnWidths) +
      $this->geometry->paddingRight;
    $this->clampScroll();
    $this->changed = true;
  }

  protected function bodyHeight(): int {
    return max(0, $this->geometry->innerHeight - $this->rowHeight);
  }

  protected function maxScrollY(): int {
    return max(0, $this->rowCount * $this->rowHeight - $this->bodyHeight());
  }

  protected function maxScrollX(): int {
    return max(0, array_sum($this->columnWidths) - $this->geometry->innerWidth);
  }

  protected function clampScroll(): void {
    $this->scrollX = max(0, min($this->scrollX, $this->maxScrollX()));
    $this->scrollY = max(0, min($this->scrollY, $this->maxScrollY()));
  }

  protected function reloadVisibleChunk(): void {
    if ($this->file === false || $this->rowCount === 0) {
      return;
    }
    $first = $this->firstVisibleRow();
    if ($first < $this->chunkStart || $first >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($first);
    }
  }

  protected function firstVisibleRow(): int {
    return max(0, (int)floor($this->scrollY / $this->rowHeight));
  }

  protected function lastVisibleRow(): int {
    if ($this->bodyHeight() === 0) {
      return min($this->rowCount - 1, $this->firstVisibleRow() + 300);
    }
    $visible = (int)ceil($this->bodyHeight() / $this->rowHeight) + 2;
    return min($this->rowCount - 1, $this->firstVisibleRow() + $visible);
  }

  protected function columnCount(): int {
    return max(1, count($this->columnWidths), count($this->header));
  }

  protected function clampCursor(): void {
    $this->cursorRow = max(0, min($this->cursorRow, max(0, $this->rowCount - 1)));
    $this->cursorColumn = max(0, min($this->cursorColumn, $this->columnCount() - 1));
  }

  protected function clampAnchor(): void {
    $this->anchorRow = max(0, min($this->anchorRow, max(0, $this->rowCount - 1)));
    $this->anchorColumn = max(0, min($this->anchorColumn, $this->columnCount() - 1));
  }

  protected function resetSelection(bool $select = false): void {
    if ($select) {
      $this->clampAnchor();
      return;
    }
    $this->anchorRow = $this->cursorRow;
    $this->anchorColumn = $this->cursorColumn;
  }

  protected function cellIsSelected(int $row, int $column): bool {
    if ($row < 0) {
      return false;
    }
    [$row1, $col1, $row2, $col2] = $this->getSelection();
    return $row >= $row1 && $row <= $row2 && $column >= $col1 && $column <= $col2;
  }

  protected function cursorCellX(): int {
    return $this->columnX($this->cursorColumn);
  }

  protected function columnX(int $column): int {
    $x = 0;
    for ($i = 0; $i < $column; $i++) {
      $x += $this->columnWidths[$i] ?? $this->minFieldWidth;
    }
    return $x;
  }

  protected function keepCursorOnScreen(): void {
    $this->clampCursor();
    $this->clampAnchor();
    $cellTop = $this->cursorRow * $this->rowHeight;
    $cellBottom = $cellTop + $this->rowHeight;
    $visibleTop = $this->scrollY;
    $visibleBottom = $this->scrollY + $this->bodyHeight();
    if ($cellTop < $visibleTop) {
      $this->scrollY = $cellTop;
    } else if ($cellBottom > $visibleBottom) {
      $this->scrollY = $cellBottom - $this->bodyHeight();
    }

    $cellLeft = $this->cursorCellX();
    $cellWidth = $this->columnWidths[$this->cursorColumn] ?? $this->minFieldWidth;
    $cellRight = $cellLeft + $cellWidth;
    if ($cellWidth > $this->geometry->innerWidth) {
      if ($cellLeft > $this->scrollX) {
        $this->scrollX = $cellLeft;
      } else if ($cellRight < $this->scrollX + $this->geometry->innerWidth) {
        $this->scrollX = $cellRight - $this->geometry->innerWidth;
      }
    } else if ($cellLeft < $this->scrollX) {
      $this->scrollX = $cellLeft;
    } else if ($cellRight > $this->scrollX + $this->geometry->innerWidth) {
      $this->scrollX = $cellRight - $this->geometry->innerWidth;
    }
    $this->clampScroll();
  }

  protected function displaySegments(mixed $value, int $innerWidth): array {
    if ($value === null) {
      return [['text' => 'NULL', 'variant' => 'tableNull']];
    }

    $text = (string)$value;
    $multiline = str_contains($text, "\n") || str_contains($text, "\r");
    if ($multiline) {
      $parts = preg_split("/\r\n|\r|\n/", $text, 2);
      $text = $parts[0] ?? '';
    }

    $markerLength = $multiline ? 1 : 0;
    $maxChars = max(0, (int)floor($innerWidth / $this->letterWidth));
    $truncated = mb_strlen($text) + $markerLength > $maxChars;
    if ($truncated) {
      $markerLength += 1;
      $text = mb_substr($text, 0, max(0, $maxChars - $markerLength));
    }

    $segments = [];
    if ($text !== '') {
      $segments[] = ['text' => $text, 'variant' => null];
    }
    if ($truncated) {
      $segments[] = ['text' => '…', 'variant' => 'tableMarker'];
    }
    if ($multiline) {
      $segments[] = ['text' => '>', 'variant' => 'tableMarker'];
    }
    if (empty($segments)) {
      $segments[] = ['text' => '', 'variant' => null];
    }
    return $segments;
  }

  protected function colorForVariant(?string $variant, array $fallback): array {
    if ($variant === 'tableNull' || $variant === 'tableMarker') {
      return $this->styleFor('Word', ['Word:' . $variant])->get('color');
    }
    return $fallback;
  }

  protected function visibleColumnRange(): array {
    $columns = [];
    $x = 0;
    $left = $this->scrollX;
    $right = $this->scrollX + $this->geometry->innerWidth;
    for ($i = 0; $i < $this->columnCount(); $i++) {
      $width = $this->columnWidths[$i] ?? $this->minFieldWidth;
      if ($x + $width > $left && $x < $right) {
        $columns[] = [$i, $x, $width];
      }
      if ($x > $right) {
        break;
      }
      $x += $width;
    }
    return $columns;
  }

  protected function firstVisibleColumn(): int {
    $range = $this->visibleColumnRange();
    if (empty($range)) {
      return 0;
    }
    return $range[0][0];
  }

  protected function moveToFirstVisibleColumn(): void {
    $first = $this->firstVisibleColumn();
    if ($this->cursorColumn === $first && $this->scrollX > 0) {
      $width = $this->columnWidths[$first] ?? $this->minFieldWidth;
      $right = $this->columnX($first) + $width;
      if ($right < $this->scrollX + $this->geometry->innerWidth) {
        $this->scrollX = $right - $this->geometry->innerWidth;
      } else {
        $this->scrollX = max($this->scrollX - $this->geometry->innerWidth, min($this->scrollX - 1, $right - $this->geometry->innerWidth + 1));
      }
      $this->clampScroll();
      $first = $this->firstVisibleColumn();
    }
    $this->cursorColumn = $first;
  }

  protected function lastVisibleColumn(): int {
    $range = $this->visibleColumnRange();
    if (empty($range)) {
      return $this->columnCount() - 1;
    }
    return $range[count($range) - 1][0];
  }

  protected function moveToLastVisibleColumn(): void {
    $last = $this->lastVisibleColumn();
    if ($this->cursorColumn === $last && $this->scrollX < $this->maxScrollX()) {
      $left = $this->columnX($last);
      $width = $this->columnWidths[$last] ?? $this->minFieldWidth;
      $right = $left + $width;
      if ($left > $this->scrollX) {
        $this->scrollX = $left;
      } else {
        $this->scrollX = min($this->scrollX + $this->geometry->innerWidth, max($this->scrollX + 1, $right - 1));
      }
      $this->clampScroll();
      $last = $this->lastVisibleColumn();
    }
    $this->cursorColumn = $last;
  }

  protected function visibleCellsForRow(int $dataRow, array $values, int $y): array {
    $isHeader = $dataRow < 0;
    $isActive = $this->isActive();
    $baseStyle = $isHeader ? $this->styleFor('TableHeader') : $this->styleFor('TableCell');
    $cursorStyle = $this->styleFor('TableCell', [$isActive ? 'TableCell:cursor' : 'TableCell:inactive-cursor']);
    $selectionStyle = $this->styleFor('TableCell', [$isActive ? 'TableCell:selection' : 'TableCell:inactive-selection']);
    $activeHeaderStyle = $this->styleFor('TableHeader', ['TableHeader:active']);
    $cells = [];
    foreach ($this->visibleColumnRange() as [$column, $columnX, $width]) {
      $style = $isHeader && $isActive ? $activeHeaderStyle : $baseStyle;
      if (!$isHeader && $this->cellIsSelected($dataRow, $column)) {
        $style = $selectionStyle;
      }
      if (!$isHeader && $dataRow === $this->cursorRow && $column === $this->cursorColumn) {
        $style = $cursorStyle;
      }
      $paddingLeft = $style->get('paddingLeft', $this->geometry);
      $paddingRight = $style->get('paddingRight', $this->geometry);
      $borderStyle = $baseStyle;
      $borderRight = $borderStyle->get('borderRight', $this->geometry);
      $color = $style->get('color');
      $innerWidth = max(0, $width - $paddingLeft - $paddingRight - $borderRight);
      $cells[] = [
        'row' => $dataRow,
        'column' => $column,
        'value' => $values[$column] ?? null,
        'x' => $this->geometry->borderLeft + $this->geometry->paddingLeft + $columnX - $this->scrollX,
        'y' => $y,
        'width' => $width,
        'height' => $this->rowHeight,
        'textX' => $this->geometry->borderLeft + $this->geometry->paddingLeft + $columnX - $this->scrollX + $paddingLeft,
        'textY' => $y + $style->get('paddingTop', $this->geometry),
        'innerWidth' => $innerWidth,
        'borderRight' => $borderRight,
        'backgroundColor' => $style->get('backgroundColor'),
        'color' => $color,
        'borderColor' => $this->styleValue(
          $borderStyle,
          'borderColorRight',
          $this->styleValue($this->style, 'borderColorRight', $color)
        ),
        'selected' => !$isHeader && $this->cellIsSelected($dataRow, $column),
        'cursor' => !$isHeader && $dataRow === $this->cursorRow && $column === $this->cursorColumn,
        'segments' => $this->displaySegments($values[$column] ?? null, $innerWidth)
      ];
    }
    return $cells;
  }

  protected function visibleBodyCells(): array {
    $cells = [];
    $baseY = $this->geometry->borderTop + $this->geometry->paddingTop + $this->rowHeight - ($this->scrollY % $this->rowHeight);
    for ($dataRow = $this->firstVisibleRow(); $dataRow <= $this->lastVisibleRow(); $dataRow++) {
      $chunkIndex = $dataRow - $this->chunkStart;
      $values = $this->chunk[$chunkIndex] ?? [];
      $y = $baseY + ($dataRow - $this->firstVisibleRow()) * $this->rowHeight;
      $cells = array_merge($cells, $this->visibleCellsForRow($dataRow, $values, $y));
    }
    return $cells;
  }

  protected function drawTableCells(array $cells, ?int $clipTop = null, ?int $clipBottom = null): void {
    foreach ($cells as $cell) {
      $y1 = $clipTop === null ? $cell['y'] : max($cell['y'], $clipTop);
      $y2 = $clipBottom === null ? $cell['y'] + $cell['height'] : min($cell['y'] + $cell['height'], $clipBottom);
      if ($y1 >= $y2) {
        continue;
      }
      $this->texture->drawFillRect(
        $cell['x'],
        $y1,
        $cell['x'] + $cell['width'],
        $y2,
        $cell['backgroundColor']
      );
      if ($cell['borderRight'] > 0 && $cell['borderColor'] !== 'transparent') {
        $this->texture->drawFillRect(
          $cell['x'] + $cell['width'] - $cell['borderRight'],
          $y1,
          $cell['x'] + $cell['width'],
          $y2,
          $cell['borderColor']
        );
      }
    }
    foreach ($cells as $cell) {
      if ($clipTop !== null && $cell['textY'] < $clipTop) {
        continue;
      }
      if ($clipBottom !== null && $cell['textY'] + $this->lineHeight > $clipBottom) {
        continue;
      }
      $x = $cell['textX'];
      $maxX = $cell['textX'] + $cell['innerWidth'];
      foreach ($cell['segments'] as $segment) {
        $fg = $this->colorForVariant($segment['variant'], $cell['color']);
        foreach (preg_split('//u', $segment['text'], -1, PREG_SPLIT_NO_EMPTY) as $glyph) {
          if ($x + $this->letterWidth > $maxX) {
            break 2;
          }
          $this->drawGlyphAt($glyph, $x, $cell['textY'], $fg);
          $x += $this->letterWidth;
        }
      }
    }
  }

  protected function draw(): void {
    if (
      $this->texture === false ||
      $this->textureWidth !== $this->geometry->width ||
      $this->textureHeight !== $this->geometry->height
    ) {
      $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $this->style->get('backgroundColor'));
      $this->textureWidth = $this->geometry->width;
      $this->textureHeight = $this->geometry->height;
    }
    $this->texture->activate();
    $sdl = SDL::$instance->sdl;
    $background = $this->style->get('backgroundColor');
    $sdl->SDL_SetRenderDrawColor($this->renderer, $background[0], $background[1], $background[2], $background[3] ?? 0xff);
    $sdl->SDL_RenderClear($this->renderer);
    $bodyTop = $this->geometry->borderTop + $this->geometry->paddingTop + $this->rowHeight;
    $bodyBottom = $this->geometry->borderTop + $this->geometry->paddingTop + $this->geometry->innerHeight;
    $headerStyle = $this->isActive() ? $this->styleFor('TableHeader', ['TableHeader:active']) : $this->styleFor('TableHeader');
    $this->texture->drawFillRect(
      $this->geometry->borderLeft + $this->geometry->paddingLeft,
      $this->geometry->borderTop + $this->geometry->paddingTop,
      $this->geometry->borderLeft + $this->geometry->paddingLeft + $this->geometry->innerWidth,
      $this->geometry->borderTop + $this->geometry->paddingTop + $this->rowHeight,
      $headerStyle->get('backgroundColor')
    );
    $this->drawTableCells($this->visibleCellsForRow(-1, $this->header, $this->geometry->borderTop + $this->geometry->paddingTop));
    $this->drawTableCells($this->visibleBodyCells(), $bodyTop, $bodyBottom);
    $this->changed = false;
  }

  protected function render(): Texture|false {
    if ($this->display === false || $this->texture === false) {
      return false;
    }
    new Border($this->texture, $this->geometry, $this->ancestor->geometry, $this->style);
    new Scrollbar(
      $this->texture,
      $this->scrollX,
      $this->scrollY,
      array_sum($this->columnWidths),
      $this->rowHeight + $this->rowCount * $this->rowHeight,
      $this->geometry,
      $this->style
    );
    return $this->texture;
  }

  public function keyPressHandler($element, $event): bool {
    $action = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $page = max(1, (int)floor($this->bodyHeight() / $this->rowHeight));
    $oldChunkStart = $this->chunkStart;
    switch ($action) {
      case Action::MOVE_UP:
        $this->cursorRow--;
        $this->resetSelection();
        break;
      case Action::SELECT_UP:
        $this->cursorRow--;
        $this->resetSelection(true);
        break;
      case Action::MOVE_DOWN:
        $this->cursorRow++;
        $this->resetSelection();
        break;
      case Action::SELECT_DOWN:
        $this->cursorRow++;
        $this->resetSelection(true);
        break;
      case Action::PAGE_UP:
        $this->cursorRow -= $page;
        $this->resetSelection();
        break;
      case Action::SELECT_PAGE_UP:
        $this->cursorRow -= $page;
        $this->resetSelection(true);
        break;
      case Action::PAGE_DOWN:
        $this->cursorRow += $page;
        $this->resetSelection();
        break;
      case Action::SELECT_PAGE_DOWN:
        $this->cursorRow += $page;
        $this->resetSelection(true);
        break;
      case Action::MOVE_FIRST:
        $this->moveToFirstVisibleColumn();
        $this->resetSelection();
        break;
      case Action::SELECT_FIRST:
        $this->moveToFirstVisibleColumn();
        $this->resetSelection(true);
        break;
      case Action::MOVE_LAST:
        $this->moveToLastVisibleColumn();
        $this->resetSelection();
        break;
      case Action::SELECT_LAST:
        $this->moveToLastVisibleColumn();
        $this->resetSelection(true);
        break;
      case Action::MOVE_LEFT:
        $this->cursorColumn--;
        $this->resetSelection();
        break;
      case Action::SELECT_LEFT:
        $this->cursorColumn--;
        $this->resetSelection(true);
        break;
      case Action::MOVE_RIGHT:
        $this->cursorColumn++;
        $this->resetSelection();
        break;
      case Action::SELECT_RIGHT:
        $this->cursorColumn++;
        $this->resetSelection(true);
        break;
      case Action::MOVE_START:
        $this->cursorColumn = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_START:
        $this->cursorColumn = 0;
        $this->resetSelection(true);
        break;
      case Action::MOVE_END:
        $this->cursorColumn = $this->columnCount() - 1;
        $this->resetSelection();
        break;
      case Action::SELECT_END:
        $this->cursorColumn = $this->columnCount() - 1;
        $this->resetSelection(true);
        break;
      case Action::LEVEL_UP:
        $this->cursorRow = 0;
        $this->cursorColumn = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_LEVEL_UP:
        $this->cursorRow = 0;
        $this->cursorColumn = 0;
        $this->resetSelection(true);
        break;
      case Action::LEVEL_DOWN:
        $this->cursorRow = $this->rowCount - 1;
        $this->cursorColumn = $this->columnCount() - 1;
        $this->resetSelection();
        break;
      case Action::SELECT_LEVEL_DOWN:
        $this->cursorRow = $this->rowCount - 1;
        $this->cursorColumn = $this->columnCount() - 1;
        $this->resetSelection(true);
        break;
      case Action::SELECT_ALL:
        $this->cursorRow = $this->rowCount - 1;
        $this->cursorColumn = $this->columnCount() - 1;
        $this->anchorRow = 0;
        $this->anchorColumn = 0;
        break;
      default:
        return false;
    }
    $this->keepCursorOnScreen();
    $this->reloadVisibleChunk();
    $this->changed = true;
    if ($this->renderer !== false) {
      if ($oldChunkStart !== $this->chunkStart) {
        $this->recalculateGeometry();
        Element::immediateRender($this, false);
      } else {
        Element::immediateRender($this, false);
      }
    }
    return true;
  }

}
