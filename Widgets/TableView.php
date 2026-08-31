<?php

namespace SPTK\Widgets;

use SPTK\Core\Clipboard;
use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Displays scrollable tabular data from memory rows or chunked TSV sources.
 */
class TableView extends Element {

  protected const CELL_TRUNCATION_MARKER = '~';
  protected const CELL_MULTILINE_MARKER = 'v';
  protected const ALIGNMENTS = ['left', 'right', 'center'];
  protected array $header = [];
  protected array $rows = [];
  protected array $explicitWidths = [];
  protected array $columnAlignments = [];
  protected array $rawColumnWidths = [];
  protected array $columnWidths = [];
  protected int $cursorRow = 0;
  protected int $cursorColumn = 0;
  protected int $anchorRow = 0;
  protected int $anchorColumn = 0;
  protected string $selectionMode = 'cell';
  protected int $scrollRow = 0;
  protected int $scrollColumnOffset = 0;
  protected bool $rowNumbers = false;
  protected int $rowNumberColumnWidth = 0;
  protected int $chunkSize = 10000;
  protected int $chunkStart = 0;
  protected array $chunk = [];
  protected bool $rowCursor = false;
  protected bool $columnSeparatorLines = true;
  protected int $lineCount = 0;
  protected int $rowCount = 0;
  protected array $lineOffsets = [];
  protected string|false $file = false;
  protected mixed $handle = null;
  protected string $source = 'array';
  protected int $minColumnWidth = 6;
  protected bool $widthsMeasured = false;
  protected array|false $searchState = false;
  protected Color|string|int|null $fieldFg = null;
  protected Color|string|int|null $fieldBg = null;
  protected $onChange = null;

  public function __construct(
    string $name = '',
    array $header = [],
    array $rows = [],
    array $widths = []
  ) {
    parent::__construct($name);
    $this->focusable = true;
    $this->setRows($header, $rows, $widths);
  }

  public function setRows(array $header, array $rows, array $widths = []): static {
    $this->source = 'array';
    $this->file = false;
    $this->handle = null;
    $this->header = array_values($header);
    $this->rows = array_map(fn($row) => array_values((array)$row), $rows);
    $this->explicitWidths = array_values($widths);
    $this->rowCount = count($this->rows);
    $this->lineCount = $this->rowCount + ($this->header === [] ? 0 : 1);
    $this->chunkStart = 0;
    $this->chunk = $this->rows;
    $this->resetStateForSource();
    return $this;
  }

  public function setTsvFile(string $path, array $options = []): static {
    $this->setOptions($options);
    if (!is_file($path)) {
      return $this;
    }
    $this->source = 'file';
    $this->file = $path;
    $this->handle = null;
    $this->rows = [];
    $this->explicitWidths = array_values($options['widths'] ?? []);
    $this->scanFile();
    $this->loadChunk(0);
    $this->resetStateForSource();
    return $this;
  }

  public function setTsvHandle(mixed $handle, array $options = []): static {
    $this->setOptions($options);
    if (!is_resource($handle)) {
      return $this;
    }
    $this->source = 'handle';
    $this->file = false;
    $this->handle = $handle;
    $this->rows = [];
    $this->explicitWidths = array_values($options['widths'] ?? []);
    $this->scanHandle();
    $this->loadChunk(0);
    $this->resetStateForSource();
    return $this;
  }

  public function setOptions(array $options): static {
    if (array_key_exists('chunkSize', $options)) {
      $this->chunkSize = max(1, (int)$options['chunkSize']);
    }
    if (array_key_exists('minColumnWidth', $options)) {
      $this->minColumnWidth = max(1, (int)$options['minColumnWidth']);
    }
    if (array_key_exists('minFieldWidth', $options)) {
      $this->minColumnWidth = max(1, (int)$options['minFieldWidth']);
    }
    if (array_key_exists('rowNumbers', $options)) {
      $this->rowNumbers = $this->boolOption($options['rowNumbers']);
    }
    if (array_key_exists('rowCursor', $options)) {
      $this->setRowCursor($this->boolOption($options['rowCursor']));
    }
    if (array_key_exists('columnSeparatorLines', $options)) {
      $this->columnSeparatorLines = $this->boolOption($options['columnSeparatorLines']);
    }
    if (array_key_exists('columnAlignments', $options)) {
      $this->setColumnAlignments((array)$options['columnAlignments']);
    } else if (array_key_exists('alignments', $options)) {
      $this->setColumnAlignments((array)$options['alignments']);
    }
    if (array_key_exists('onChange', $options) && is_callable($options['onChange'])) {
      $this->onChange = $options['onChange'];
    }
    $this->widthsMeasured = false;
    $this->invalidateRender();
    return $this;
  }

  public function setFieldColors(Color|string|int|null $fg = null, Color|string|int|null $bg = null): static {
    $this->fieldFg = $fg;
    $this->fieldBg = $bg;
    $this->invalidateRender();
    return $this;
  }

  public function setRowNumbers(bool $rowNumbers): static {
    $this->rowNumbers = $rowNumbers;
    $this->widthsMeasured = false;
    $this->syncScroll();
    $this->invalidateRender();
    return $this;
  }

  public function rowNumbers(): bool {
    return $this->rowNumbers;
  }

  public function setRowCursor(bool $rowCursor = true): static {
    if ($this->rowCursor !== $rowCursor) {
      $this->rowCursor = $rowCursor;
      $this->cursorColumn = 0;
      $this->anchorColumn = 0;
      $this->resetSelection();
      $this->syncScroll();
      $this->invalidateRender();
    }
    return $this;
  }

  public function rowCursor(): bool {
    return $this->rowCursor;
  }

  public function setColumnSeparatorLines(bool $columnSeparatorLines = true): static {
    if ($this->columnSeparatorLines !== $columnSeparatorLines) {
      $this->columnSeparatorLines = $columnSeparatorLines;
      $this->invalidateRender();
    }
    return $this;
  }

  public function columnSeparatorLines(): bool {
    return $this->columnSeparatorLines;
  }

  public function rowCount(): int {
    return $this->rowCount;
  }

  public function lineCount(): int {
    return $this->lineCount;
  }

  public function columnCount(): int {
    return max(1, count($this->header), count($this->columnWidths));
  }

  public function header(): array {
    return $this->header;
  }

  public function chunkStart(): int {
    return $this->chunkStart;
  }

  public function chunk(): array {
    return $this->chunk;
  }

  public function columnWidths(): array {
    $this->measureColumnWidths();
    return $this->columnWidths;
  }

  public function setColumnAlignments(array $alignments): static {
    $this->columnAlignments = [];
    foreach ($alignments as $column => $alignment) {
      $index = $this->normalizeColumnKey($column);
      $alignment = $this->normalizeAlignment((string)$alignment);
      if ($index !== null && $alignment !== null) {
        $this->columnAlignments[$index] = $alignment;
      }
    }
    $this->invalidateRender();
    return $this;
  }

  public function columnAlignments(): array {
    return $this->columnAlignments;
  }

  public function cursorRow(): int {
    return $this->cursorRow;
  }

  public function cursorColumn(): int {
    return $this->cursorColumn;
  }

  public function setCursor(int $row, int $column = 0): static {
    $this->cursorRow = $row;
    $this->cursorColumn = $this->rowCursor ? 0 : $column;
    $this->resetSelection();
    $this->syncScroll();
    $this->loadVisibleChunk();
    $this->triggerOnChange();
    $this->invalidateRender();
    return $this;
  }

  public function activeCellValue(): mixed {
    if ($this->rowCount === 0) {
      return false;
    }
    $this->clampCursor();
    $row = $this->rowValues($this->cursorRow);
    return $row === false ? false : ($row[$this->cursorColumn] ?? null);
  }

  public function activeRowValues(): array|false {
    if ($this->rowCount === 0) {
      return false;
    }
    $this->clampCursor();
    return $this->rowValues($this->cursorRow);
  }

  public function rowValues(int $row): array|false {
    if ($this->rowCount === 0 || $row < 0 || $row >= $this->rowCount) {
      return false;
    }
    if ($this->source === 'array') {
      return $this->rows[$row] ?? false;
    }
    if ($row < $this->chunkStart || $row >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($row);
    }
    return $this->chunk[$row - $this->chunkStart] ?? false;
  }

  public function rowRangeValues(int $first, int $last): array {
    if ($this->rowCount === 0) {
      return [];
    }
    $first = max(0, min($first, $this->rowCount - 1));
    $last = max(0, min($last, $this->rowCount - 1));
    if ($first > $last) {
      [$first, $last] = [$last, $first];
    }
    $rows = [];
    for ($row = $first; $row <= $last; $row++) {
      $values = $this->rowValues($row);
      if ($values !== false) {
        $rows[] = $values;
      }
    }
    return $rows;
  }

  public function selection(): array {
    [$row1, $col1, $row2, $col2] = $this->rawSelection();
    if ($this->selectionMode === 'row') {
      return [$row1, 0, $row2, $this->columnCount() - 1];
    }
    if ($this->selectionMode === 'column') {
      return [0, $col1, max(0, $this->rowCount - 1), $col2];
    }
    return [$row1, $col1, $row2, $col2];
  }

  public function selectionMode(): string {
    return $this->selectionMode;
  }

  public function selectRow(?int $row = null): static {
    $row ??= $this->cursorRow;
    return $this->selectRows($row, $row);
  }

  public function selectRows(int $first, int $last): static {
    if ($this->rowCount === 0) {
      return $this->clearSelection();
    }
    $this->selectionMode = 'row';
    $this->anchorRow = max(0, min($first, $this->rowCount - 1));
    $this->cursorRow = max(0, min($last, $this->rowCount - 1));
    $this->anchorColumn = 0;
    $this->cursorColumn = max(0, min($this->cursorColumn, $this->columnCount() - 1));
    $this->syncAfterMove();
    return $this;
  }

  public function selectColumn(?int $column = null): static {
    $column ??= $this->cursorColumn;
    return $this->selectColumns($column, $column);
  }

  public function selectColumns(int $first, int $last): static {
    $columns = $this->columnCount();
    if ($columns <= 0) {
      return $this->clearSelection();
    }
    $this->selectionMode = 'column';
    $this->anchorColumn = max(0, min($first, $columns - 1));
    $this->cursorColumn = max(0, min($last, $columns - 1));
    $this->anchorRow = 0;
    $this->cursorRow = max(0, min($this->cursorRow, max(0, $this->rowCount - 1)));
    $this->syncAfterMove();
    return $this;
  }

  public function selectedRows(): array {
    [$row1, , $row2, ] = $this->selection();
    return $this->selectionMode === 'column' ? range(0, max(0, $this->rowCount - 1)) : range($row1, $row2);
  }

  public function selectedColumns(): array {
    [, $col1, , $col2] = $this->selection();
    return $this->selectionMode === 'row' ? range(0, $this->columnCount() - 1) : range($col1, $col2);
  }

  public function clearSelection(): static {
    $this->resetSelection();
    $this->invalidateRender();
    return $this;
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
      $this->invalidateRender();
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

  public function clearSearch(): static {
    $this->searchState = false;
    $this->invalidateRender();
    return $this;
  }

  public function searchState(): array {
    return $this->searchState === false ? [] : $this->searchState;
  }

  public function searchColumns(mixed $columns): array {
    return $this->normalizeSearchColumns($columns);
  }

  public function handle(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    if ($this->isCopyEvent($event)) {
      return $this->copySelection();
    }
    if ($this->isSelectAllEvent($event)) {
      if ($this->rowCount === 0) {
        return false;
      }
      $this->selectionMode = $this->rowCursor ? 'row' : 'cell';
      $this->anchorRow = 0;
      $this->anchorColumn = 0;
      $this->cursorRow = $this->rowCount - 1;
      $this->cursorColumn = $this->rowCursor ? 0 : $this->columnCount() - 1;
      $this->syncAfterMove();
      return true;
    }

    $old = [$this->cursorRow, $this->cursorColumn, $this->anchorRow, $this->anchorColumn, $this->scrollRow, $this->scrollColumnOffset];
    $select = $this->modifier($event, 'shift');
    $ctrl = $this->modifier($event, 'ctrl');
    $page = max(1, $this->bodyRows());
    if (InputAction::selectRow($event, 'table')) {
      $this->selectRow();
      $this->triggerOnChange();
      return true;
    }
    if (InputAction::selectColumn($event, 'table')) {
      if ($this->rowCursor) {
        return false;
      }
      $this->selectColumn();
      $this->triggerOnChange();
      return true;
    }
    if ($this->rowCursor) {
      return $this->handleRowCursorKey($event, $old, $select, $ctrl, $page);
    }
    if (InputAction::left($event, 'table')) {
      $this->cursorColumn--;
    } else if (InputAction::right($event, 'table')) {
      $this->cursorColumn++;
    } else if (InputAction::up($event, 'table')) {
      $this->cursorRow--;
    } else if (InputAction::down($event, 'table')) {
      $this->cursorRow++;
    } else if (InputAction::pageUp($event, 'table')) {
      if ($ctrl) {
        $this->cursorRow = 0;
      } else {
        $this->cursorRow -= $page;
      }
    } else if (InputAction::pageDown($event, 'table')) {
      if ($ctrl) {
        $this->cursorRow = $this->rowCount - 1;
      } else {
        $this->cursorRow += $page;
      }
    } else if (InputAction::home($event, 'table')) {
      if ($ctrl) {
        $this->pageToFirstHorizontalEdge();
      } else {
        $this->cursorColumn = 0;
      }
    } else if (InputAction::end($event, 'table')) {
      if ($ctrl) {
        $this->pageToLastHorizontalEdge();
      } else {
        $this->cursorColumn = $this->columnCount() - 1;
      }
    } else {
      return false;
    }
    $this->resetSelection($select);
    $this->syncAfterMove();
    if ($old !== [$this->cursorRow, $this->cursorColumn, $this->anchorRow, $this->anchorColumn, $this->scrollRow, $this->scrollColumnOffset]) {
      $this->triggerOnChange();
    }
    return true;
  }

  protected function handleRowCursorKey(InputEvent $event, array $old, bool $select, bool $ctrl, int $page): bool {
    if (InputAction::up($event, 'table')) {
      $this->cursorRow--;
    } else if (InputAction::down($event, 'table')) {
      $this->cursorRow++;
    } else if (InputAction::pageUp($event, 'table')) {
      $this->cursorRow = $ctrl ? 0 : $this->cursorRow - $page;
    } else if (InputAction::pageDown($event, 'table')) {
      $this->cursorRow = $ctrl ? $this->rowCount - 1 : $this->cursorRow + $page;
    } else if (InputAction::home($event, 'table')) {
      $this->cursorRow = 0;
    } else if (InputAction::end($event, 'table')) {
      $this->cursorRow = $this->rowCount - 1;
    } else {
      return false;
    }
    $this->cursorColumn = 0;
    $this->resetSelection($select);
    $this->syncAfterMove();
    if ($old !== [$this->cursorRow, $this->cursorColumn, $this->anchorRow, $this->anchorColumn, $this->scrollRow, $this->scrollColumnOffset]) {
      $this->triggerOnChange();
    }
    return true;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->fieldFg(), $this->fieldBg());
    $this->measureColumnWidths();
    $this->syncScroll();
    $this->loadVisibleChunk();
    if ($this->frame->height <= 0 || $this->frame->width <= 0) {
      return;
    }
    $this->paintHeader($target);
    for ($screenRow = 0; $screenRow < $this->bodyRows(); $screenRow++) {
      $rowIndex = $this->scrollRow + $screenRow;
      if ($rowIndex >= $this->rowCount) {
        break;
      }
      $row = $this->rowValues($rowIndex);
      if ($row !== false) {
        $this->paintRow($target, $this->frame->y + 1 + $screenRow, $row, false, $rowIndex);
      }
    }
    $this->paintScrollbars($target);
  }

  protected function paintHeader(RenderTarget $target): void {
    $target->fill(new Rect($this->frame->x, $this->frame->y, $this->frame->width, 1), ' ', '#000000', '#aaaaaa');
    $this->paintRow($target, $this->frame->y, $this->header, true, -1);
  }

  protected function paintRow(RenderTarget $target, int $y, array $row, bool $header, int $rowIndex): void {
    $right = $this->frame->right();
    if (!$header && $this->rowCursor && $rowIndex === $this->cursorRow) {
      [$cursorFg, $cursorBg] = $this->cursorColors();
      $target->fill(new Rect($this->frame->x, $y, $this->frame->width, 1), ' ', $cursorFg, $cursorBg);
    }
    $x = $this->frame->x - $this->scrollColumnOffset;
    foreach ($this->displayColumns() as [$displayColumn, $column, $width]) {
      if ($x >= $right) {
        break;
      }
      $cellRight = $x + $width;
      if ($cellRight > $this->frame->x && $width > 0) {
        $clipX = max($x, $this->frame->x);
        $clipWidth = min($cellRight, $right) - $clipX;
        $rowNumber = $column === false;
        $value = $rowNumber ? ($header ? '#' : (string)($rowIndex + 1)) : ($row[$column] ?? null);
        [$fg, $bg] = $this->cellColors($header, $rowNumber, $rowIndex, $column, $value);
        $target->fill(new Rect($clipX, $y, max(0, $clipWidth), 1), ' ', $fg, $bg);
        if ($rowNumber) {
          $this->writeRightAlignedCellValue($target, $x, $y, $width, $value, $fg, $bg);
        } else {
          $this->writeCellValue($target, $x, $y, $width, $value, $fg, $bg, $this->columnAlignment($column));
        }
        if (!$rowNumber && $cellRight - 1 >= $this->frame->x && $cellRight - 1 < $right && $displayColumn < $this->displayColumnCount() - 1) {
          $rowCursorCell = $this->rowCursor && $rowIndex === $this->cursorRow;
          $separatorFg = $header || $rowNumber ? '#000000' : ($rowCursorCell ? $fg : $this->fieldFg());
          $separatorBg = $header || $rowNumber ? '#aaaaaa' : ($rowCursorCell ? $bg : $this->fieldBg());
          $target->put($cellRight - 1, $y, ' ', $separatorFg, $separatorBg);
          $this->paintColumnSeparatorLine($target, $cellRight - 1, $y, $separatorFg);
        }
      }
      $x += $width;
    }
  }

  protected function cellColors(bool $header, bool $rowNumber, int $row, int|false $column, mixed $value): array {
    if ($header) {
      return ['#000000', '#aaaaaa'];
    }
    if ($this->rowCursor && $row === $this->cursorRow) {
      return $this->cursorColors();
    }
    if ($rowNumber) {
      return ['#000000', '#aaaaaa'];
    }
    $fg = $this->fieldFg();
    $bg = $this->fieldBg();
    if ($this->cellMatchesSearch($row, $column, $value)) {
      $fg = '#000000';
      $bg = '#aaaa00';
    }
    if ($this->cellIsSelected($row, $column)) {
      $fg = $this->focused ? $this->theme->cursorFg : $this->fieldFg();
      $bg = $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg;
    }
    if ($this->cellIsActiveSearchMatch($row, $column)) {
      $fg = '#000000';
      $bg = '#ffff00';
    }
    if ($this->cellIsCursor($row, $column)) {
      [$fg, $bg] = $this->cursorColors();
    }
    return [$fg, $bg];
  }

  protected function cursorColors(): array {
    return [
      $this->focused ? $this->theme->cursorFg : $this->fieldFg(),
      $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg,
    ];
  }

  protected function paintColumnSeparatorLine(RenderTarget $target, int $x, int $y, Color|string|int $color): void {
    if (!$this->columnSeparatorLines || !$target instanceof SurfaceRenderTarget) {
      return;
    }
    $target->drawLine($x + 0.5, $y, $x + 0.5, $y + 1, $color, 1);
  }

  protected function fieldFg(): Color {
    return Color::from($this->fieldFg ?? $this->theme->fg);
  }

  protected function fieldBg(): Color {
    $color = Color::from($this->fieldBg ?? $this->theme->bg);
    if ($this->focused) {
      return $this->brightenColor($color);
    }
    return $color;
  }

  protected function brightenColor(Color $color): Color {
    return new Color(
      min(255, (int)round($color->r * 1.2)),
      min(255, (int)round($color->g * 1.2)),
      min(255, (int)round($color->b * 1.2)),
      $color->a
    );
  }

  protected function writeCellValue(RenderTarget $target, int $x, int $y, int $width, mixed $value, Color|string|int $fg, Color|string|int $bg, string $alignment = 'left'): void {
    $inner = max(0, $width - 1);
    if ($inner <= 0) {
      return;
    }
    $segments = $this->displaySegments($value, $inner);
    $contentLength = $this->segmentsLength($segments);
    $padding = max(0, $inner - $contentLength);
    $leftPadding = match ($alignment) {
      'right' => $padding,
      'center' => intdiv($padding, 2),
      default => 0,
    };
    $start = max($this->frame->x, $x);
    $offset = $start - $x;
    $screenX = $start + max(0, $leftPadding - $offset);
    $position = $leftPadding;
    foreach ($segments as $segment) {
      $text = $segment['text'];
      $length = mb_strlen($text);
      if ($offset >= $position + $length) {
        $position += $length;
        continue;
      }
      $segmentOffset = max(0, $offset - $position);
      $visible = mb_substr($text, $segmentOffset, max(0, min($length - $segmentOffset, $this->frame->right() - $screenX)));
      if ($visible !== '') {
        $target->write($screenX, $y, $visible, $segment['marker'] ? $this->theme->markerFg : $fg, $bg);
        $screenX += mb_strlen($visible);
      }
      $position += $length;
      if ($screenX >= $this->frame->right()) {
        break;
      }
    }
  }

  protected function segmentsLength(array $segments): int {
    $length = 0;
    foreach ($segments as $segment) {
      $length += mb_strlen((string)($segment['text'] ?? ''));
    }
    return $length;
  }

  protected function writeRightAlignedCellValue(RenderTarget $target, int $x, int $y, int $width, mixed $value, Color|string|int $fg, Color|string|int $bg): void {
    if ($width <= 0) {
      return;
    }
    $contentWidth = max(0, $width - 1);
    $text = mb_substr((string)$value, 0, $contentWidth);
    $text = str_pad($text, $contentWidth, ' ', STR_PAD_LEFT) . ' ';
    $start = max($this->frame->x, $x);
    $offset = $start - $x;
    $visible = mb_substr($text, $offset, max(0, min($width - $offset, $this->frame->right() - $start)));
    if ($visible !== '') {
      $target->write($start, $y, $visible, $fg, $bg);
    }
  }

  protected function displaySegments(mixed $value, int $limit): array {
    if ($limit <= 0) {
      return [];
    }
    if ($value === null) {
      return [['text' => mb_substr('NULL', 0, $limit), 'marker' => true]];
    }
    $clipped = $this->clipCellText((string)$value, $limit);
    $segments = [];
    if ($clipped['text'] !== '') {
      $segments[] = ['text' => $clipped['text'], 'marker' => false];
    }
    if ($clipped['truncated']) {
      $segments[] = ['text' => self::CELL_TRUNCATION_MARKER, 'marker' => true];
    }
    if ($clipped['multiline']) {
      $segments[] = ['text' => self::CELL_MULTILINE_MARKER, 'marker' => true];
    }
    return $segments;
  }

  protected function clipCellText(string $value, int $maxChars): array {
    $multiline = str_contains($value, "\n") || str_contains($value, "\r");
    $text = $value;
    if ($multiline) {
      $parts = preg_split("/\r\n|\r|\n/", $text, 2);
      $text = $parts[0] ?? '';
    }
    $markerLength = $multiline ? 1 : 0;
    $truncated = mb_strlen($text) + $markerLength > $maxChars;
    if ($truncated) {
      $markerLength++;
      $text = mb_substr($text, 0, max(0, $maxChars - $markerLength));
    }
    return ['text' => $text, 'truncated' => $truncated, 'multiline' => $multiline];
  }

  protected function resetStateForSource(): void {
    $this->cursorRow = 0;
    $this->cursorColumn = 0;
    $this->anchorRow = 0;
    $this->anchorColumn = 0;
    $this->scrollRow = 0;
    $this->scrollColumnOffset = 0;
    $this->selectionMode = 'cell';
    $this->searchState = false;
    $this->widthsMeasured = false;
    $this->measureColumnWidths();
    $this->clampCursor();
    $this->resetSelection();
    $this->invalidateRender();
  }

  protected function measureColumnWidths(): void {
    if ($this->widthsMeasured && $this->explicitWidths === []) {
      $this->limitColumnWidthsToFrame();
      return;
    }
    if ($this->explicitWidths !== []) {
      $this->rawColumnWidths = $this->explicitColumnWidths();
    } else {
      $widths = [];
      foreach (array_merge([$this->header], $this->measurementRows()) as $row) {
        foreach ($row as $i => $field) {
          $widths[$i] = max($widths[$i] ?? $this->minColumnWidth, $this->measuredCellWidth($field), $this->minColumnWidth);
        }
      }
      $columns = max(count($this->header), count($widths));
      for ($i = 0; $i < $columns; $i++) {
        $widths[$i] = $widths[$i] ?? $this->minColumnWidth;
      }
      ksort($widths);
      $this->rawColumnWidths = array_values($widths);
    }
    $this->widthsMeasured = true;
    $this->limitColumnWidthsToFrame();
  }

  protected function measurementRows(): array {
    return $this->source === 'array' ? $this->rows : $this->chunk;
  }

  protected function measuredCellWidth(mixed $value): int {
    if ($value === null) {
      return max($this->minColumnWidth, 5);
    }
    $text = (string)$value;
    if (str_contains($text, "\n") || str_contains($text, "\r")) {
      $parts = preg_split("/\r\n|\r|\n/", $text, 2);
      $text = ($parts[0] ?? '') . self::CELL_MULTILINE_MARKER;
    }
    return max($this->minColumnWidth, mb_strlen($text) + 2);
  }

  protected function explicitColumnWidths(): array {
    $available = $this->frame->width > 0 ? max(1, $this->frame->width - ($this->rowNumbers ? $this->rowNumberColumnWidth : 0)) : 0;
    $widths = [];
    foreach ($this->explicitWidths as $width) {
      if (is_string($width) && preg_match('/^\s*(\d+(?:\.\d+)?)%\s*$/', $width, $match) === 1) {
        $widths[] = max(1, $available > 0 ? (int)floor($available * ((float)$match[1] / 100)) : (int)$match[1]);
      } else {
        $widths[] = max(1, (int)$width);
      }
    }
    return $widths;
  }

  protected function limitColumnWidthsToFrame(): void {
    $this->columnWidths = $this->rawColumnWidths;
    $digits = max(1, mb_strlen((string)max(1, $this->rowCount)));
    $this->rowNumberColumnWidth = $digits + 1;
    if ($this->frame->width <= 0) {
      return;
    }
    if ($this->tableContentWidth($this->columnWidths) <= $this->frame->width) {
      return;
    }
    $max = max($this->minColumnWidth, (int)floor($this->frame->width * 0.5));
    foreach ($this->columnWidths as $i => $width) {
      $this->columnWidths[$i] = min($width, $max);
    }
  }

  protected function tableContentWidth(?array $widths = null): int {
    return array_sum($widths ?? $this->columnWidths) + ($this->rowNumbers ? $this->rowNumberColumnWidth : 0);
  }

  protected function bodyRows(): int {
    return max(0, $this->frame->height - 1);
  }

  protected function needsVerticalScrollbar(): bool {
    return $this->rowCount > $this->bodyRows();
  }

  protected function needsHorizontalScrollbar(): bool {
    return $this->tableContentWidth() > $this->frame->width;
  }

  protected function paintScrollbars(RenderTarget $target): void {
    if ($this->needsVerticalScrollbar() && $this->bodyRows() > 0) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->right() - 1, $this->frame->y + 1, 1, $this->bodyRows()),
        $this->scrollRow,
        $this->bodyRows(),
        max(1, $this->rowCount),
        'vertical',
        $this->theme->markerFg
      );
    }
    if ($this->needsHorizontalScrollbar() && $this->frame->height > 1) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->x, $this->frame->bottom() - 1, max(0, $this->frame->width), 1),
        $this->scrollColumnOffset,
        max(1, $this->frame->width),
        max(1, $this->tableContentWidth()),
        'horizontal',
        $this->theme->markerFg
      );
    }
  }

  protected function syncAfterMove(): void {
    $this->syncScroll();
    $this->loadVisibleChunk();
    $this->invalidateRender();
  }

  protected function syncScroll(): void {
    $this->measureColumnWidths();
    $this->clampCursor();
    $this->clampAnchor();
    $bodyRows = $this->bodyRows();
    if ($bodyRows <= 0) {
      $this->scrollRow = $this->cursorRow;
    } else if ($this->cursorRow < $this->scrollRow) {
      $this->scrollRow = $this->cursorRow;
    } else if ($this->cursorRow >= $this->scrollRow + $bodyRows) {
      $this->scrollRow = $this->cursorRow - $bodyRows + 1;
    }
    $this->scrollRow = max(0, min($this->scrollRow, max(0, $this->rowCount - max(1, $bodyRows))));

    [$left, $right] = $this->cursorColumnBounds();
    $viewport = max(1, $this->frame->width);
    if ($left < $this->scrollColumnOffset) {
      $this->scrollColumnOffset = $left;
    } else if ($right > $this->scrollColumnOffset + $viewport) {
      $this->scrollColumnOffset = $right - $viewport;
    }
    $this->scrollColumnOffset = max(0, min($this->scrollColumnOffset, max(0, $this->tableContentWidth() - $viewport)));
  }

  protected function cursorColumnBounds(): array {
    $left = $this->columnX($this->cursorColumn);
    $width = $this->columnWidths[$this->cursorColumn] ?? $this->minColumnWidth;
    if ($this->rowNumbers && $this->cursorColumn === 0) {
      $left = 0;
      $width += $this->rowNumberColumnWidth;
    }
    return [$left, $left + $width];
  }

  protected function columnX(int $column): int {
    $x = 0;
    for ($i = 0; $i < $this->displayColumnForData($column); $i++) {
      $x += $this->displayColumnWidth($i);
    }
    return $x;
  }

  protected function displayColumns(): array {
    $columns = [];
    for ($display = 0; $display < $this->displayColumnCount(); $display++) {
      $columns[] = [$display, $this->dataColumnForDisplay($display), $this->displayColumnWidth($display)];
    }
    return $columns;
  }

  protected function displayColumnCount(): int {
    return $this->columnCount() + ($this->rowNumbers ? 1 : 0);
  }

  protected function displayColumnWidth(int $displayColumn): int {
    $column = $this->dataColumnForDisplay($displayColumn);
    return $column === false ? $this->rowNumberColumnWidth : ($this->columnWidths[$column] ?? $this->minColumnWidth);
  }

  protected function dataColumnForDisplay(int $displayColumn): int|false {
    if ($this->rowNumbers) {
      return $displayColumn === 0 ? false : $displayColumn - 1;
    }
    return $displayColumn;
  }

  protected function displayColumnForData(int $column): int {
    return $column + ($this->rowNumbers ? 1 : 0);
  }

  protected function columnAlignment(int|false $column): string {
    if ($column === false) {
      return 'right';
    }
    return $this->columnAlignments[$column] ?? 'left';
  }

  protected function normalizeColumnKey(int|string $column): ?int {
    if (is_int($column) || ctype_digit((string)$column)) {
      $index = (int)$column;
      return $index >= 0 && $index < $this->columnCount() ? $index : null;
    }
    $index = array_search((string)$column, $this->header, true);
    return $index === false ? null : $index;
  }

  protected function normalizeAlignment(string $alignment): ?string {
    $alignment = strtolower(trim($alignment));
    return in_array($alignment, self::ALIGNMENTS, true) ? $alignment : null;
  }

  protected function firstVisibleColumn(): int {
    $x = 0;
    for ($display = 0; $display < $this->displayColumnCount(); $display++) {
      $width = $this->displayColumnWidth($display);
      $column = $this->dataColumnForDisplay($display);
      if ($column !== false && $x + $width > $this->scrollColumnOffset) {
        return $column;
      }
      $x += $width;
    }
    return 0;
  }

  protected function lastVisibleColumn(): int {
    $x = 0;
    $right = $this->scrollColumnOffset + max(1, $this->frame->width);
    $last = 0;
    for ($display = 0; $display < $this->displayColumnCount(); $display++) {
      $width = $this->displayColumnWidth($display);
      $column = $this->dataColumnForDisplay($display);
      if ($column !== false && $x < $right) {
        $last = $column;
      }
      $x += $width;
    }
    return $last;
  }

  protected function moveToFirstVisibleColumn(): void {
    $this->cursorColumn = $this->firstVisibleColumn();
    if ($this->cursorColumn === 0) {
      $this->scrollColumnOffset = 0;
    }
  }

  protected function moveToLastVisibleColumn(): void {
    $this->cursorColumn = $this->lastVisibleColumn();
  }

  protected function pageToFirstHorizontalEdge(): void {
    $first = $this->firstVisibleColumn();
    if ($this->cursorColumn === $first && $this->scrollColumnOffset > 0) {
      $this->scrollColumnOffset = max(0, $this->scrollColumnOffset - max(1, $this->frame->width));
      $first = $this->firstVisibleColumn();
    }
    $this->cursorColumn = $first;
    if ($this->cursorColumn === 0) {
      $this->scrollColumnOffset = 0;
    }
  }

  protected function pageToLastHorizontalEdge(): void {
    $last = $this->lastVisibleColumn();
    $viewport = max(1, $this->frame->width);
    $maxOffset = max(0, $this->tableContentWidth() - $viewport);
    if ($this->cursorColumn === $last && $this->scrollColumnOffset < $maxOffset) {
      $this->scrollColumnOffset = min($maxOffset, $this->scrollColumnOffset + $viewport);
      $last = $this->lastVisibleColumn();
    }
    $this->cursorColumn = $last;
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
    $this->selectionMode = $this->rowCursor ? 'row' : 'cell';
    $this->anchorRow = $this->cursorRow;
    $this->anchorColumn = $this->rowCursor ? 0 : $this->cursorColumn;
  }

  protected function cellIsSelected(int $row, int $column): bool {
    if ($row < 0) {
      return false;
    }
    [$row1, $col1, $row2, $col2] = $this->selection();
    return $row >= $row1 && $row <= $row2 && $column >= $col1 && $column <= $col2;
  }

  protected function cellIsCursor(int $row, int|false $column): bool {
    if ($column === false) {
      return false;
    }
    if ($this->rowCursor) {
      return $row === $this->cursorRow;
    }
    return $row === $this->cursorRow && $column === $this->cursorColumn;
  }

  protected function rawSelection(): array {
    return [
      min($this->cursorRow, $this->anchorRow),
      min($this->cursorColumn, $this->anchorColumn),
      max($this->cursorRow, $this->anchorRow),
      max($this->cursorColumn, $this->anchorColumn),
    ];
  }

  protected function buildSearchState(string $text, array $options): array|false {
    if ($text === '' || $this->rowCount === 0) {
      return false;
    }
    $regexp = $this->boolOption($options['regexp'] ?? false);
    $caseSensitive = $this->boolOption($options['caseSensitive'] ?? false);
    $pattern = false;
    if ($regexp) {
      $pattern = '~' . str_replace('~', '\~', $text) . '~u' . ($caseSensitive ? '' : 'i');
      if (!$this->validSearchPattern($pattern)) {
        return false;
      }
    }
    $columns = $this->normalizeSearchColumns($options['columns'] ?? null);
    if ($columns === []) {
      return false;
    }
    return [
      'text' => $text,
      'regexp' => $regexp,
      'caseSensitive' => $caseSensitive,
      'pattern' => $pattern,
      'columns' => $columns,
      'active' => false,
    ];
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

  protected function validSearchPattern(string $pattern): bool {
    set_error_handler(function(): void {
    });
    $valid = preg_match($pattern, '') !== false;
    restore_error_handler();
    return $valid;
  }

  protected function findMatch(array $state, int $row, int $column, int $direction, bool $includeStart): array|false {
    $columns = $this->columnCount();
    $total = $this->rowCount * $columns;
    if ($total <= 0) {
      return false;
    }
    $row = max(0, min($row, $this->rowCount - 1));
    $column = max(0, min($column, $columns - 1));
    $start = $row * $columns + $column;
    for ($step = $includeStart ? 0 : 1; $step < $total + ($includeStart ? 0 : 1); $step++) {
      $index = ($start + $direction * $step) % $total;
      if ($index < 0) {
        $index += $total;
      }
      $match = $this->searchMatchAt($state, (int)floor($index / $columns), $index % $columns);
      if ($match !== false) {
        return $match;
      }
    }
    return false;
  }

  protected function searchMatchAt(array $state, int $rowIndex, int $column): array|false {
    if (!in_array($column, $state['columns'], true)) {
      return false;
    }
    $row = $this->rowValues($rowIndex);
    if ($row === false) {
      return false;
    }
    $value = $row[$column] ?? null;
    if (!$this->searchMatchesValue($state, $value)) {
      return false;
    }
    return ['row' => $rowIndex, 'column' => $column, 'value' => $value, 'header' => $this->header[$column] ?? ''];
  }

  protected function searchMatchesValue(array $state, mixed $value): bool {
    $text = $value === null ? 'NULL' : (string)$value;
    if ($state['regexp']) {
      return preg_match($state['pattern'], $text) === 1;
    }
    return $state['caseSensitive'] ? str_contains($text, $state['text']) : stripos($text, $state['text']) !== false;
  }

  protected function moveToSearchMatch(array $match): void {
    $this->cursorRow = (int)$match['row'];
    $this->cursorColumn = (int)$match['column'];
    $this->resetSelection();
    $this->syncAfterMove();
    $this->triggerOnChange();
  }

  protected function cellMatchesSearch(int $row, int $column, mixed $value): bool {
    return $this->searchState !== false &&
      in_array($column, $this->searchState['columns'], true) &&
      $this->searchMatchesValue($this->searchState, $value);
  }

  protected function cellIsActiveSearchMatch(int $row, int $column): bool {
    return $this->searchState !== false &&
      is_array($this->searchState['active']) &&
      (int)$this->searchState['active']['row'] === $row &&
      (int)$this->searchState['active']['column'] === $column;
  }

  protected function copySelection(): bool {
    if ($this->rowCount === 0) {
      return false;
    }
    [$row1, $col1, $row2, $col2] = $this->selection();
    if ($row1 === $row2 && $col1 === $col2) {
      Clipboard::set($this->copyCellValue($this->activeCellValue()));
      return true;
    }
    $lines = [];
    for ($row = $row1; $row <= $row2; $row++) {
      $values = $this->rowValues($row);
      if ($values === false) {
        continue;
      }
      $fields = [];
      for ($column = $col1; $column <= $col2; $column++) {
        $fields[] = $this->copyCellValue($values[$column] ?? null);
      }
      $lines[] = implode("\t", $fields);
    }
    Clipboard::set(implode("\n", $lines));
    return true;
  }

  protected function copyCellValue(mixed $value): string {
    if ($value === null || $value === false) {
      return 'NULL';
    }
    return str_replace(["\\", "\r", "\n", "\t"], ["\\\\", "\\r", "\\n", "\\t"], (string)$value);
  }

  protected function isCopyEvent(InputEvent $event): bool {
    return InputAction::copy($event, 'table');
  }

  protected function isSelectAllEvent(InputEvent $event): bool {
    return InputAction::selectAll($event, 'table');
  }

  protected function modifier(InputEvent $event, string $modifier): bool {
    return ($event->modifiers[$modifier] ?? false) === true;
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
    $this->scanOpenHandle($handle);
    fclose($handle);
  }

  protected function scanHandle(): void {
    $this->header = [];
    $this->lineCount = 0;
    $this->rowCount = 0;
    $this->lineOffsets = [];
    rewind($this->handle);
    $this->scanOpenHandle($this->handle);
  }

  protected function scanOpenHandle(mixed $handle): void {
    while (($offset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
      if ($this->lineCount % $this->chunkSize === 0) {
        $this->lineOffsets[$this->lineCount] = $offset;
      }
      if ($this->lineCount === 0) {
        $this->header = $this->parseLine($line);
      }
      $this->lineCount++;
    }
    $this->rowCount = max(0, $this->lineCount - 1);
  }

  protected function loadVisibleChunk(): void {
    if ($this->source === 'array' || $this->rowCount === 0) {
      return;
    }
    if ($this->scrollRow < $this->chunkStart || $this->scrollRow >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($this->scrollRow);
    }
  }

  protected function loadChunk(int $row): void {
    if ($this->source === 'array') {
      $this->chunkStart = 0;
      $this->chunk = $this->rows;
      return;
    }
    $row = max(0, min($row, max(0, $this->rowCount - 1)));
    $this->chunkStart = (int)(floor($row / $this->chunkSize) * $this->chunkSize);
    $targetLine = $this->chunkStart + 1;
    $seekLine = $this->nearestIndexedLine($targetLine);
    $handle = $this->source === 'file' ? fopen($this->file, 'rb') : $this->handle;
    if ($handle === false || $handle === null) {
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
    if ($this->source === 'file' && is_resource($handle)) {
      fclose($handle);
    }
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
      if ($char === '\\') {
        $escaping = true;
      } else if ($char === "\t") {
        $fields[] = $field === '\N' ? null : $field;
        $field = '';
      } else {
        $field .= $char;
      }
    }
    if ($escaping) {
      $field .= '\\';
    }
    $fields[] = $field === '\N' ? null : $field;
    return $fields;
  }

  protected function boolOption(mixed $value): bool {
    return $value === true || $value === 'true' || $value === 1 || $value === '1';
  }

  protected function triggerOnChange(): void {
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

}
