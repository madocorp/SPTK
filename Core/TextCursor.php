<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;

/** Tracks a text caret, selection anchor, and preferred display column. */
final class TextCursor {

  private array $caret = [0, 0];
  private array $anchor = [0, 0];
  private int|false $preferredColumn = false;

  /** Track a cursor through the supplied logical lines. */
  public function __construct(private array &$lines, private readonly int $tabSize = 8) {
    if ($lines === []) {
      $lines = [''];
    }
  }

  /** Return the caret row and grapheme column. */
  public function position(): array {
    return $this->caret;
  }

  /** Return the caret and anchor for selection state inspection. */
  public function selectionState(): array {
    return ['caret' => $this->caret, 'anchor' => $this->anchor, 'preferredColumn' => $this->preferredColumn];
  }

  /** Restore a previously captured caret and selection state. */
  public function restoreState(array $state): void {
    $this->caret = $state['caret'] ?? [0, 0];
    $this->anchor = $state['anchor'] ?? $this->caret;
    $this->setPosition($this->caret[0], $this->caret[1], true);
    $this->preferredColumn = $state['preferredColumn'] ?? false;
    $this->anchor[0] = max(0, min(count($this->lines) - 1, (int)$this->anchor[0]));
    $this->anchor[1] = max(0, min($this->lineLength($this->anchor[0]), (int)$this->anchor[1]));
  }

  /** Set the caret and optionally extend the current selection. */
  public function setPosition(int $row, int $column, bool $select = false): void {
    $this->caret[0] = max(0, min(count($this->lines) - 1, $row));
    $this->caret[1] = max(0, min($this->lineLength($this->caret[0]), $column));
    $this->preferredColumn = TextMetrics::width(TextMetrics::slice($this->lines[$this->caret[0]], 0, $this->caret[1]), $this->tabSize);
    if (!$select) {
      $this->collapse();
    }
  }

  /** Select all graphemes and line breaks in the document. */
  public function selectAll(): void {
    $row = count($this->lines) - 1;
    $this->anchor = [0, 0];
    $this->caret = [$row, $this->lineLength($row)];
  }

  /** Clear the selection while keeping the caret in place. */
  public function collapse(): void {
    $this->anchor = $this->caret;
  }

  /** Report whether the anchor and caret differ. */
  public function hasSelection(): bool {
    return $this->caret !== $this->anchor;
  }

  /** Return the ordered anchor and caret positions. */
  public function range(): array {
    return $this->compare($this->caret, $this->anchor) <= 0
      ? [...$this->caret, ...$this->anchor]
      : [...$this->anchor, ...$this->caret];
  }

  /** Return selected positions with an exclusive end for slicing. */
  public function selectionRange(): array {
    [$startRow, $startColumn, $endRow, $endColumn] = $this->range();
    if ($endColumn < $this->lineLength($endRow)) {
      $endColumn++;
    } else if ($endRow < count($this->lines) - 1) {
      $endRow++;
      $endColumn = 0;
    }
    return [$startRow, $startColumn, $endRow, $endColumn];
  }

  /** Return selected text, or the grapheme at the caret when selection is empty. */
  public function selectedText(): string {
    if (!$this->hasSelection()) {
      [$row, $column] = $this->caret;
      if ($column === $this->lineLength($row) && $row < count($this->lines) - 1) {
        return "\n";
      }
      return TextMetrics::slice($this->lines[$row], $column, 1);
    }
    [$startRow, $startColumn, $endRow, $endColumn] = $this->selectionRange();
    $selected = [];
    for ($row = $startRow; $row <= $endRow; $row++) {
      $start = $row === $startRow ? $startColumn : 0;
      $end = $row === $endRow ? $endColumn : $this->lineLength($row);
      $selected[] = TextMetrics::slice($this->lines[$row] ?? '', $start, $end - $start);
    }
    return implode("\n", $selected);
  }

  /** Move left, collapsing a selection to its leading edge unless extending. */
  public function moveLeft(bool $select = false): void {
    if (!$select && $this->hasSelection()) {
      [$row, $column] = $this->range();
      $this->setPosition($row, $column);
      return;
    }
    if ($this->caret[1] > 0) {
      $this->caret[1]--;
    } else if ($this->caret[0] > 0) {
      $this->caret[0]--;
      $this->caret[1] = $this->lineLength($this->caret[0]);
    }
    $this->afterMove($select);
  }

  /** Move right, collapsing a selection to its trailing edge unless extending. */
  public function moveRight(bool $select = false): void {
    if (!$select && $this->hasSelection()) {
      [, , $row, $column] = $this->range();
      $this->setPosition($row, $column);
      return;
    }
    if ($this->caret[1] < $this->lineLength($this->caret[0])) {
      $this->caret[1]++;
    } else if ($this->caret[0] < count($this->lines) - 1) {
      $this->caret[0]++;
      $this->caret[1] = 0;
    }
    $this->afterMove($select);
  }

  /** Move up while retaining the preferred display column. */
  public function moveUp(bool $select = false, int $rows = 1): void {
    $this->moveVertical(-$rows, $select);
  }

  /** Move down while retaining the preferred display column. */
  public function moveDown(bool $select = false, int $rows = 1): void {
    $this->moveVertical($rows, $select);
  }

  /** Move to the start of the current logical line. */
  public function moveLineStart(bool $select = false): void {
    $this->caret[1] = 0;
    $this->afterMove($select);
  }

  /** Move to the end of the current logical line. */
  public function moveLineEnd(bool $select = false): void {
    $this->caret[1] = $this->lineLength($this->caret[0]);
    $this->afterMove($select);
  }

  /** Move to the start of the document. */
  public function moveDocumentStart(bool $select = false): void {
    $this->caret = [0, 0];
    $this->afterMove($select);
  }

  /** Move to the end of the document. */
  public function moveDocumentEnd(bool $select = false): void {
    $row = count($this->lines) - 1;
    $this->caret = [$row, $this->lineLength($row)];
    $this->afterMove($select);
  }

  /** Move vertically and preserve the anchor when extending selection. */
  private function moveVertical(int $distance, bool $select): void {
    if ($this->preferredColumn === false) {
      $this->preferredColumn = TextMetrics::width(TextMetrics::slice($this->lines[$this->caret[0]], 0, $this->caret[1]), $this->tabSize);
    }
    $this->caret[0] = max(0, min(count($this->lines) - 1, $this->caret[0] + $distance));
    $this->caret[1] = 0;
    while ($this->caret[1] < $this->lineLength($this->caret[0])
      && TextMetrics::width(TextMetrics::slice($this->lines[$this->caret[0]], 0, $this->caret[1] + 1), $this->tabSize) <= $this->preferredColumn) {
      $this->caret[1]++;
    }
    if (!$select) {
      $this->collapse();
    }
  }

  /** Update preferred column and selection anchor after horizontal movement. */
  private function afterMove(bool $select): void {
    $this->preferredColumn = TextMetrics::width(TextMetrics::slice($this->lines[$this->caret[0]], 0, $this->caret[1]), $this->tabSize);
    if (!$select) {
      $this->collapse();
    }
  }

  /** Compare row and column positions lexicographically. */
  private function compare(array $left, array $right): int {
    return $left[0] <=> $right[0] ?: $left[1] <=> $right[1];
  }

  /** Return the grapheme count of a logical line. */
  private function lineLength(int $row): int {
    return TextMetrics::length($this->lines[$row] ?? '');
  }

}
