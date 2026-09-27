<?php

namespace SPTK\Widgets\Table;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;

/** Displays a table with a fixed header and an active cell cursor. */
final class Table extends Widget {

  use WidgetEventEmitter;

  private TableData $data;
  private Painter $painter;
  private Selection $selection;
  private Redraw $redraw;
  private array $specifiedWidths = [];
  private array $rawWidths = [];
  private array $widths = [];
  private int $cursorRow = 0;
  private int $cursorColumn = 0;
  private int $rowScroll = 0;
  private int $columnScroll = 0;
  private int $viewportWidth = 1;
  private int $viewportHeight = 1;
  private bool $active = false;

  /** Create a table from header and row records. */
  public function __construct(array $header = [], array $rows = [], array $widths = [], private Style $style = new Style(), private bool $rowNumbers = false) {
    $this->data = new TableData();
    $this->painter = new Painter($style);
    $this->selection = new Selection();
    $this->redraw = new Redraw();
    $this->setRows($header, $rows, $widths);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Replace the table data and reset its cursor and scroll. */
  public function setRows(array $header, array $rows, array $widths = []): void {
    $this->data->setRows($header, $rows);
    $this->setWidths($widths);
    $this->resetCursor();
  }

  /** Read an escaped TSV file with the first line as its header. */
  public function setTsvFile(string $path, array $widths = []): void {
    $this->data->loadFile($path);
    $this->setWidths($widths);
    $this->resetCursor();
  }

  /** Return the header fields. */
  public function header(): array {
    return $this->data->header();
  }

  /** Return the number of data rows. */
  public function rowCount(): int {
    return $this->data->count();
  }

  /** Return a data row or false for an invalid index. */
  public function rowValues(int $row): array|false {
    return $this->data->row($row);
  }

  /** Return the measured column widths. */
  public function columnWidths(): array {
    return $this->widths;
  }

  /** Return the active row index. */
  public function cursorRow(): int {
    return $this->cursorRow;
  }

  /** Return the active column index. */
  public function cursorColumn(): int {
    return $this->cursorColumn;
  }

  /** Return the field beneath the cursor, or false when the table is empty. */
  public function activeCellValue(): string|false|null {
    $row = $this->data->row($this->cursorRow);
    return $row === false ? false : ($row[$this->cursorColumn] ?? null);
  }

  /** Return the row beneath the cursor, or false when the table is empty. */
  public function activeRowValues(): array|false {
    return $this->data->row($this->cursorRow);
  }

  /** Set the active cell without emitting a user change event. */
  public function setCursor(int $row, int $column = 0): void {
    $this->cursorRow = max(0, min(max(0, $this->data->count() - 1), $row));
    $this->cursorColumn = max(0, min(max(0, $this->data->columns() - 1), $column));
    $this->selection->reset($this->cursorRow, $this->cursorColumn);
    $this->syncScroll();
    $this->redraw->full();
  }

  /** Return inclusive rectangular selection bounds. */
  public function selection(): array {
    return $this->selection->bounds();
  }

  /** Select a range of cells without emitting a user event. */
  public function selectCells(int $firstRow, int $firstColumn, int $lastRow, int $lastColumn): void {
    $this->setCursor($firstRow, $firstColumn);
    $this->moveCursor($lastRow, $lastColumn, true);
  }

  /** Copy the selected cells as escaped TSV. */
  public function copySelection(): bool {
    return $this->selection->copy($this->data);
  }

  /** Report whether the table currently receives keyboard input. */
  public function active(): bool {
    return $this->active;
  }

  /** Return the tile's background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Suggest the width of the complete table. */
  public function preferredWidth(): ?int {
    return array_sum($this->rawWidths) + ($this->rowNumbers ? strlen((string)max(1, $this->rowCount())) + 2 : 0);
  }

  /** Suggest one header row plus all data rows. */
  public function preferredHeight(): ?int {
    return $this->rowCount() + 1;
  }

  /** Paint the currently visible part of the table. */
  public function paint(GridWriter $writer): void {
    $this->viewportWidth = max(1, $writer->width());
    $this->viewportHeight = max(1, $writer->height());
    $this->fitWidths();
    $this->syncScroll();
    $this->painter->paint($writer, $this->data, $this->widths, $this->rowScroll, $this->columnScroll, $this->cursorRow, $this->cursorColumn, $this->active, $this->rowNumbers, $this->selection);
    $this->redraw->painted();
  }

  /** Paint changed cursor rows when the table viewport has not moved. */
  public function paintUpdate(GridWriter $writer): bool {
    $rows = $this->redraw->rows();
    if ($rows === null || $writer->width() !== $this->viewportWidth || $writer->height() !== $this->viewportHeight) {
      return false;
    }
    if ($rows === []) {
      return true;
    }
    $this->painter->paintRows($writer, $this->data, $this->widths, $this->rowScroll, $this->columnScroll, $this->cursorRow, $this->cursorColumn, $this->active, $this->rowNumbers, $this->selection, $rows);
    $this->redraw->painted();
    return true;
  }

  /** Navigate data cells while the table is active. */
  public function handleInput(mixed $event): bool {
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
    $modifiers = KeyNormalizer::normalizeModifiers((int)$event->key->mod);
    if (($modifiers & SDL::MOD_CTRL) !== 0 && ($key === ord('c') || $key === SDL::KEY_INSERT)) {
      return $this->copySelection();
    }
    if (($modifiers & SDL::MOD_CTRL) !== 0 && $key === ord('a')) {
      $this->selection->all($this->rowCount(), $this->data->columns());
      $this->redraw->full();
      return true;
    }
    $before = [$this->cursorRow, $this->cursorColumn];
    $beforeScroll = [$this->rowScroll, $this->columnScroll];
    $beforeSelection = $this->selection->bounds();
    $page = max(1, $this->viewportHeight - 1);
    $extend = ($modifiers & SDL::MOD_SHIFT) !== 0;
    if ($key === SDL::KEY_UP) {
      $this->moveCursor($this->cursorRow - 1, $this->cursorColumn, $extend);
    } else if ($key === SDL::KEY_DOWN) {
      $this->moveCursor($this->cursorRow + 1, $this->cursorColumn, $extend);
    } else if ($key === SDL::KEY_LEFT) {
      $this->moveCursor($this->cursorRow, $this->cursorColumn - 1, $extend);
    } else if ($key === SDL::KEY_RIGHT) {
      $this->moveCursor($this->cursorRow, $this->cursorColumn + 1, $extend);
    } else if ($key === SDL::KEY_PAGEUP) {
      $this->moveCursor($this->cursorRow - $page, $this->cursorColumn, $extend);
    } else if ($key === SDL::KEY_PAGEDOWN) {
      $this->moveCursor($this->cursorRow + $page, $this->cursorColumn, $extend);
    } else if ($key === SDL::KEY_HOME) {
      $this->moveCursor($this->cursorRow, 0, $extend);
    } else if ($key === SDL::KEY_END) {
      $this->moveCursor($this->cursorRow, $this->data->columns() - 1, $extend);
    } else {
      return false;
    }
    if ($before !== [$this->cursorRow, $this->cursorColumn]) {
      $this->emit('change');
    }
    if ($extend || $beforeScroll !== [$this->rowScroll, $this->columnScroll] || $beforeSelection[0] !== $beforeSelection[2] || $beforeSelection[1] !== $beforeSelection[3]) {
      $this->redraw->full();
    } else if ($before !== [$this->cursorRow, $this->cursorColumn]) {
      $this->redraw->moved($before[0], $this->cursorRow);
    }
    return true;
  }

  /** Validate and measure explicit or automatic column widths. */
  private function setWidths(array $widths): void {
    if ($widths !== [] && count($widths) !== $this->data->columns()) {
      throw new \InvalidArgumentException('Specify a width for every Table column.');
    }
    foreach ($widths as $width) {
      if (!is_int($width) || $width < 1) {
        throw new \InvalidArgumentException('Table column widths must be positive integers.');
      }
    }
    $this->specifiedWidths = array_values($widths);
    $this->rawWidths = $this->painter->widths($this->data, $this->specifiedWidths);
    $this->widths = $this->rawWidths;
  }

  /** Cap wide columns to half the viewport when the table overflows. */
  private function fitWidths(): void {
    $this->widths = $this->rawWidths;
    $numberWidth = $this->rowNumbers ? strlen((string)max(1, $this->rowCount())) + 2 : 0;
    if ($numberWidth + array_sum($this->widths) <= $this->viewportWidth) {
      return;
    }
    $limit = max(6, intdiv($this->viewportWidth, 2));
    foreach ($this->widths as $column => $width) {
      $this->widths[$column] = min($width, $limit);
    }
  }

  /** Reset the cursor after replacing the source. */
  private function resetCursor(): void {
    $this->cursorRow = 0;
    $this->cursorColumn = 0;
    $this->rowScroll = 0;
    $this->columnScroll = 0;
    $this->selection->reset();
    $this->redraw->full();
  }

  /** Move the cursor and either extend or replace the selected rectangle. */
  private function moveCursor(int $row, int $column, bool $extend): void {
    $this->cursorRow = max(0, min(max(0, $this->data->count() - 1), $row));
    $this->cursorColumn = max(0, min(max(0, $this->data->columns() - 1), $column));
    $this->selection->move($this->cursorRow, $this->cursorColumn, $extend);
    $this->syncScroll();
  }

  /** Keep the active row and column inside the painted viewport. */
  private function syncScroll(): void {
    $bodyHeight = max(1, $this->viewportHeight - 1);
    $this->rowScroll = min($this->rowScroll, $this->cursorRow);
    $this->rowScroll = max($this->rowScroll, $this->cursorRow - $bodyHeight + 1);
    $this->rowScroll = min($this->rowScroll, max(0, $this->rowCount() - $bodyHeight));
    $numberWidth = $this->rowNumbers ? strlen((string)max(1, $this->rowCount())) + 2 : 0;
    $left = $numberWidth + array_sum(array_slice($this->widths, 0, $this->cursorColumn));
    $right = $left + ($this->widths[$this->cursorColumn] ?? 0);
    $this->columnScroll = min($this->columnScroll, max(0, $left - $numberWidth));
    $this->columnScroll = max($this->columnScroll, $right - $this->viewportWidth);
    $this->columnScroll = max(0, min($this->columnScroll, max(0, $numberWidth + array_sum($this->widths) - $this->viewportWidth)));
  }

  /** Highlight the cursor when the screen activates this tile. */
  private function activate(): void {
    $this->active = true;
    $this->redraw->full();
  }

  /** Remove the active cursor highlight when the screen releases this tile. */
  private function deactivate(): void {
    $this->active = false;
    $this->redraw->full();
  }

}
