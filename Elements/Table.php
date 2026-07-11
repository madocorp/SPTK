<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\StyleSheet;
use \SPTK\Texture;
use \SPTK\Border;
use \SPTK\Scrollbar;
use \SPTK\Clipboard;
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
  protected bool $rowNumbers = false;
  protected int $rowNumberColumnWidth = 0;
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
  protected array|false $searchState = false;

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
    return ['file', 'chunkSize', 'minFieldWidth', 'rowNumbers'];
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
    $this->searchState = false;
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

  public function setRowNumbers($value): void {
    $this->rowNumbers = $value === true || $value === 'true' || $value === 1 || $value === '1';
    $this->widthsMeasured = false;
    $this->measureColumnWidths();
    $this->changed = true;
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
    }
  }

  public function getRowNumbers(): bool {
    return $this->rowNumbers;
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

  public function search(string $text, array $options = []): array|false {
    $state = $this->buildSearchState($text, $options);
    if ($state === false) {
      return false;
    }
    $start = $options['start'] ?? [$this->cursorRow, $this->cursorColumn];
    $match = $this->findMatch($state, (int)($start[0] ?? 0), (int)($start[1] ?? 0), 1, true);
    if ($match === false) {
      $this->searchState = false;
      $this->changed = true;
      return false;
    }
    $state['active'] = $match;
    $this->searchState = $state;
    $this->moveToSearchMatch($match);
    return $match;
  }

  public function nextMatch(): array|false {
    if ($this->searchState === false) {
      return false;
    }
    $active = $this->searchState['active'] ?? ['row' => $this->cursorRow, 'column' => $this->cursorColumn];
    $match = $this->findMatch($this->searchState, (int)$active['row'], (int)$active['column'], 1, false);
    if ($match === false) {
      return false;
    }
    $this->searchState['active'] = $match;
    $this->moveToSearchMatch($match);
    return $match;
  }

  public function previousMatch(): array|false {
    if ($this->searchState === false) {
      return false;
    }
    $active = $this->searchState['active'] ?? ['row' => $this->cursorRow, 'column' => $this->cursorColumn];
    $match = $this->findMatch($this->searchState, (int)$active['row'], (int)$active['column'], -1, false);
    if ($match === false) {
      return false;
    }
    $this->searchState['active'] = $match;
    $this->moveToSearchMatch($match);
    return $match;
  }

  public function clearSearch(): void {
    $this->searchState = false;
    $this->changed = true;
  }

  public function getSearchState(): array {
    return $this->searchState === false ? [] : $this->searchState;
  }

  protected function buildSearchState(string $text, array $options): array|false {
    if ($text === '' || $this->rowCount === 0) {
      return false;
    }
    $regexp = $this->boolOption($options['regexp'] ?? false);
    $caseSensitive = $this->boolOption($options['caseSensitive'] ?? false);
    $pattern = false;
    if ($regexp) {
      $pattern = $this->searchPattern($text, $caseSensitive);
      if (!$this->validSearchPattern($pattern)) {
        return false;
      }
    }
    $columns = $this->normalizeSearchColumns($options['columns'] ?? null);
    if (empty($columns)) {
      return false;
    }
    return [
      'text' => $text,
      'regexp' => $regexp,
      'caseSensitive' => $caseSensitive,
      'pattern' => $pattern,
      'columns' => $columns,
      'active' => false
    ];
  }

  protected function boolOption(mixed $value): bool {
    return $value === true || $value === 'true' || $value === 1 || $value === '1';
  }

  protected function searchPattern(string $text, bool $caseSensitive): string {
    return '~' . str_replace('~', '\~', $text) . '~u' . ($caseSensitive ? '' : 'i');
  }

  protected function validSearchPattern(string $pattern): bool {
    set_error_handler(function() {
    });
    $valid = preg_match($pattern, '') !== false;
    restore_error_handler();
    return $valid;
  }

  protected function normalizeSearchColumns(mixed $columns): array {
    if ($columns === null || $columns === false || $columns === []) {
      return range(0, $this->columnCount() - 1);
    }
    if (!is_array($columns)) {
      $columns = [$columns];
    }
    $normalized = [];
    foreach ($columns as $column) {
      if (is_int($column) || ctype_digit((string)$column)) {
        $idx = (int)$column;
      } else {
        $idx = array_search((string)$column, $this->header, true);
        if ($idx === false) {
          continue;
        }
      }
      if ($idx >= 0 && $idx < $this->columnCount()) {
        $normalized[] = $idx;
      }
    }
    $normalized = array_values(array_unique($normalized));
    sort($normalized);
    return $normalized;
  }

  protected function searchableCellValue(mixed $value): string {
    return $value === null ? 'NULL' : (string)$value;
  }

  protected function searchMatchesValue(array $state, mixed $value): bool {
    $text = $this->searchableCellValue($value);
    if ($state['regexp']) {
      return preg_match($state['pattern'], $text) === 1;
    }
    return $state['caseSensitive'] ? str_contains($text, $state['text']) : stripos($text, $state['text']) !== false;
  }

  protected function searchMatchAt(array $state, int $rowIndex, int $column): array|false {
    if (!in_array($column, $state['columns'], true)) {
      return false;
    }
    $row = $this->rowValues($rowIndex);
    if ($row === false) {
      return false;
    }
    if (!$this->searchMatchesValue($state, $row[$column] ?? null)) {
      return false;
    }
    return [
      'row' => $rowIndex,
      'column' => $column,
      'value' => $row[$column] ?? null,
      'header' => $this->header[$column] ?? ''
    ];
  }

  protected function findMatch(array $state, int $row, int $column, int $direction, bool $includeStart): array|false {
    $total = $this->rowCount * $this->columnCount();
    if ($total <= 0) {
      return false;
    }
    $row = max(0, min($row, $this->rowCount - 1));
    $column = max(0, min($column, $this->columnCount() - 1));
    $start = $row * $this->columnCount() + $column;
    for ($step = $includeStart ? 0 : 1; $step < $total + ($includeStart ? 0 : 1); $step++) {
      $index = ($start + $direction * $step) % $total;
      if ($index < 0) {
        $index += $total;
      }
      $match = $this->searchMatchAt($state, (int)floor($index / $this->columnCount()), $index % $this->columnCount());
      if ($match !== false) {
        return $match;
      }
    }
    return false;
  }

  protected function moveToSearchMatch(array $match): void {
    $this->cursorRow = (int)$match['row'];
    $this->cursorColumn = (int)$match['column'];
    $this->resetSelection();
    $this->keepCursorOnScreen();
    $this->reloadVisibleChunk();
    $this->changed = true;
  }

  protected function cellMatchesSearch(int $row, int $column, mixed $value): bool {
    return $this->searchState !== false &&
      $row >= 0 &&
      in_array($column, $this->searchState['columns'], true) &&
      $this->searchMatchesValue($this->searchState, $value);
  }

  protected function cellIsActiveSearchMatch(int $row, int $column): bool {
    return $this->searchState !== false &&
      is_array($this->searchState['active']) &&
      (int)$this->searchState['active']['row'] === $row &&
      (int)$this->searchState['active']['column'] === $column;
  }

  protected function rowValues(int $row): array|false {
    if ($this->rowCount === 0 || $row < 0 || $row >= $this->rowCount) {
      return false;
    }
    if ($row < $this->chunkStart || $row >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($row);
    }
    return $this->chunk[$row - $this->chunkStart] ?? false;
  }

  protected function copyCellValue(mixed $value): string {
    if ($value === null) {
      return 'NULL';
    }
    return str_replace(["\\", "\r", "\n", "\t"], ["\\\\", "\\r", "\\n", "\\t"], (string)$value);
  }

  protected function copyCurrentCell(): bool {
    $value = $this->getActiveCellValue();
    if ($value === false) {
      return false;
    }
    Clipboard::set($this->copyCellValue($value));
    return true;
  }

  protected function copySelectedRange(): bool {
    [$row1, $col1, $row2, $col2] = $this->getSelection();
    $lines = [];
    $headers = [];
    for ($column = $col1; $column <= $col2; $column++) {
      $headers[] = $this->copyCellValue($this->header[$column] ?? '');
    }
    $lines[] = implode("\t", $headers);
    for ($rowIndex = $row1; $rowIndex <= $row2; $rowIndex++) {
      $row = $this->rowValues($rowIndex);
      if ($row === false) {
        continue;
      }
      $fields = [];
      for ($column = $col1; $column <= $col2; $column++) {
        $fields[] = $this->copyCellValue($row[$column] ?? null);
      }
      $lines[] = implode("\t", $fields);
    }
    Clipboard::set(implode("\n", $lines));
    return true;
  }

  protected function copySelection(): bool {
    if ($this->rowCount === 0) {
      return false;
    }
    $chunkStart = $this->chunkStart;
    $chunk = $this->chunk;
    try {
      [$row1, $col1, $row2, $col2] = $this->getSelection();
      if ($row1 === $row2 && $col1 === $col2) {
        return $this->copyCurrentCell();
      }
      return $this->copySelectedRange();
    } finally {
      $this->chunkStart = $chunkStart;
      $this->chunk = $chunk;
    }
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
    $digits = max(mb_strlen((string)max(1, $this->rowCount)), 1);
    $this->rowNumberColumnWidth = $digits * $this->letterWidth + $this->cellHorizontalChrome;
    $this->limitColumnWidthsToBox();
  }

  protected function limitColumnWidthsToBox(): void {
    if ($this->geometry->innerWidth <= 0) {
      return;
    }
    $rowNumberWidth = $this->rowNumbers ? $this->rowNumberColumnWidth : 0;
    if (array_sum($this->rawColumnWidths) + $rowNumberWidth <= $this->geometry->innerWidth) {
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
      $this->tableContentWidth() +
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
    return max(0, $this->tableContentWidth() - $this->geometry->innerWidth);
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

  protected function displayColumnCount(): int {
    return $this->columnCount() + ($this->rowNumbers ? 1 : 0);
  }

  protected function dataColumnForDisplay(int $displayColumn): int|false {
    if ($this->rowNumbers) {
      if ($displayColumn === 0) {
        return false;
      }
      return $displayColumn - 1;
    }
    return $displayColumn;
  }

  protected function displayColumnForData(int $column): int {
    return $column + ($this->rowNumbers ? 1 : 0);
  }

  protected function displayColumnWidth(int $displayColumn): int {
    $column = $this->dataColumnForDisplay($displayColumn);
    if ($column === false) {
      return $this->rowNumberColumnWidth;
    }
    return $this->columnWidths[$column] ?? $this->minFieldWidth;
  }

  protected function tableContentWidth(): int {
    return array_sum($this->columnWidths) + ($this->rowNumbers ? $this->rowNumberColumnWidth : 0);
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

  protected function cursorScrollBounds(): array {
    $left = $this->cursorCellX();
    $width = $this->columnWidths[$this->cursorColumn] ?? $this->minFieldWidth;
    if ($this->rowNumbers && $this->cursorColumn === 0) {
      $left = 0;
      $width += $this->rowNumberColumnWidth;
    }
    return [$left, $left + $width, $width];
  }

  protected function columnX(int $column): int {
    $x = 0;
    for ($i = 0; $i < $this->displayColumnForData($column); $i++) {
      $x += $this->displayColumnWidth($i);
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

    [$cellLeft, $cellRight, $cellWidth] = $this->cursorScrollBounds();
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
    for ($i = 0; $i < $this->displayColumnCount(); $i++) {
      $width = $this->displayColumnWidth($i);
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
    foreach ($range as [$displayColumn]) {
      $column = $this->dataColumnForDisplay($displayColumn);
      if ($column !== false) {
        return $column;
      }
    }
    return 0;
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
    for ($i = count($range) - 1; $i >= 0; $i--) {
      $column = $this->dataColumnForDisplay($range[$i][0]);
      if ($column !== false) {
        return $column;
      }
    }
    return $this->columnCount() - 1;
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
    $headerStyle = $this->styleFor('TableHeader');
    $cursorStyle = $this->styleFor('TableCell', [$isActive ? 'TableCell:cursor' : 'TableCell:inactive-cursor']);
    $selectionStyle = $this->styleFor('TableCell', [$isActive ? 'TableCell:selection' : 'TableCell:inactive-selection']);
    $searchStyle = $this->styleFor('TableCell', ['TableCell:search']);
    $activeSearchStyle = $this->styleFor('TableCell', ['TableCell:search-active']);
    $activeHeaderStyle = $this->styleFor('TableHeader', ['TableHeader:active']);
    $cells = [];
    foreach ($this->visibleColumnRange() as [$displayColumn, $columnX, $width]) {
      $column = $this->dataColumnForDisplay($displayColumn);
      $rowNumber = $column === false;
      $value = $rowNumber ? ($isHeader ? '#' : (string)($dataRow + 1)) : ($values[$column] ?? null);
      $style = ($isHeader || $rowNumber) && $isActive ? $activeHeaderStyle : (($isHeader || $rowNumber) ? $headerStyle : $baseStyle);
      $search = !$isHeader && !$rowNumber && $this->cellMatchesSearch($dataRow, $column, $value);
      $activeSearch = !$isHeader && !$rowNumber && $this->cellIsActiveSearchMatch($dataRow, $column);
      if ($search) {
        $style = $searchStyle;
      }
      if (!$isHeader && !$rowNumber && $this->cellIsSelected($dataRow, $column)) {
        $style = $selectionStyle;
      }
      if ($activeSearch) {
        $style = $activeSearchStyle;
      }
      if (!$isHeader && !$rowNumber && $dataRow === $this->cursorRow && $column === $this->cursorColumn) {
        $style = $activeSearch ? $activeSearchStyle : $cursorStyle;
      }
      $paddingLeft = $style->get('paddingLeft', $this->geometry);
      $paddingRight = $style->get('paddingRight', $this->geometry);
      $borderStyle = $baseStyle;
      $borderRight = $borderStyle->get('borderRight', $this->geometry);
      $color = $style->get('color');
      $innerWidth = max(0, $width - $paddingLeft - $paddingRight - $borderRight);
      $cells[] = [
        'row' => $dataRow,
        'column' => $rowNumber ? -1 : $column,
        'displayColumn' => $displayColumn,
        'rowNumber' => $rowNumber,
        'value' => $value,
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
        'search' => $search,
        'activeSearch' => $activeSearch,
        'selected' => !$isHeader && !$rowNumber && $this->cellIsSelected($dataRow, $column),
        'cursor' => !$isHeader && !$rowNumber && $dataRow === $this->cursorRow && $column === $this->cursorColumn,
        'segments' => $this->displaySegments($value, $innerWidth)
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
    $sdl = SDL::$instance->sdl;
    if ($clipTop !== null || $clipBottom !== null) {
      self::$sdlRect->x = 0;
      self::$sdlRect->y = $clipTop ?? 0;
      self::$sdlRect->w = $this->geometry->width;
      self::$sdlRect->h = ($clipBottom ?? $this->geometry->height) - self::$sdlRect->y;
      $sdl->SDL_SetRenderClipRect($this->renderer, self::$sdlRectAddr);
    }
    foreach ($cells as $cell) {
      if ($clipTop !== null && $cell['textY'] + $this->lineHeight <= $clipTop) {
        continue;
      }
      if ($clipBottom !== null && $cell['textY'] >= $clipBottom) {
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
    if ($clipTop !== null || $clipBottom !== null) {
      $sdl->SDL_SetRenderClipRect($this->renderer, null);
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
      $this->tableContentWidth(),
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
      case Action::COPY:
        return $this->copySelection();
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
