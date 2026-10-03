<?php

namespace SPTK\Widgets\Table;

use SPTK\Core\Clipboard;

/** Tracks a rectangular cell selection and copies it as escaped TSV. */
final class Selection {

  private int $anchorRow = 0;
  private int $anchorColumn = 0;
  private int $row = 0;
  private int $column = 0;

  /** Reset the selection to one cell. */
  public function reset(int $row = 0, int $column = 0): void {
    $this->anchorRow = $row;
    $this->anchorColumn = $column;
    $this->row = $row;
    $this->column = $column;
  }

  /** Extend or replace the selection at a cursor position. */
  public function move(int $row, int $column, bool $extend): void {
    if (!$extend) {
      $this->anchorRow = $row;
      $this->anchorColumn = $column;
    }
    $this->row = $row;
    $this->column = $column;
  }

  /** Select the complete data rectangle. */
  public function all(int $rows, int $columns): void {
    $this->anchorRow = 0;
    $this->anchorColumn = 0;
    $this->row = max(0, $rows - 1);
    $this->column = max(0, $columns - 1);
  }

  /** Return inclusive start and end row and column indices. */
  public function bounds(): array {
    return [min($this->anchorRow, $this->row), min($this->anchorColumn, $this->column), max($this->anchorRow, $this->row), max($this->anchorColumn, $this->column)];
  }

  /** Check whether one data cell is selected beyond the cursor. */
  public function includes(int $row, int $column): bool {
    [$firstRow, $firstColumn, $lastRow, $lastColumn] = $this->bounds();
    return ($firstRow !== $lastRow || $firstColumn !== $lastColumn) && $row >= $firstRow && $row <= $lastRow && $column >= $firstColumn && $column <= $lastColumn;
  }

  /** Copy selected cell values to the shared clipboard. */
  public function copy(TableData $data): bool {
    if ($data->count() === 0) {
      return false;
    }
    [$firstRow, $firstColumn, $lastRow, $lastColumn] = $this->bounds();
    $lines = [];
    for ($row = $firstRow; $row <= $lastRow; $row++) {
      $values = $data->row($row);
      if ($values === false) {
        continue;
      }
      $fields = [];
      for ($column = $firstColumn; $column <= $lastColumn; $column++) {
        $value = $values[$column] ?? null;
        $fields[] = $value === null ? 'NULL' : str_replace(["\\", "\r", "\n", "\t"], ["\\\\", '\\r', '\\n', '\\t'], $value);
      }
      $lines[] = implode("\t", $fields);
    }
    Clipboard::set(implode("\n", $lines));
    return true;
  }

}
