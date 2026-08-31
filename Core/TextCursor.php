<?php

namespace SPTK\Core;

/**
 * Tracks a row/column text caret and selection anchor for line-oriented text.
 */
class TextCursor {

  protected array $caret = [0, 0];
  protected array $anchor = [0, 0];
  protected int|false $preferredColumn = false;

  public function __construct(protected array &$lines) {
    $this->clamp();
    $this->collapse();
  }

  public function position(): array {
    return [$this->caret[0], $this->caret[1]];
  }

  public function selectionState(): array {
    return [
      'caret' => $this->caret,
      'anchor' => $this->anchor,
      'preferredColumn' => $this->preferredColumn,
    ];
  }

  public function restoreState(array $state): void {
    $this->caret = $state['caret'] ?? [0, 0];
    $this->anchor = $state['anchor'] ?? $this->caret;
    $this->preferredColumn = $state['preferredColumn'] ?? false;
    $this->clamp();
    $this->anchor[0] = max(0, min(count($this->lines) - 1, (int)$this->anchor[0]));
    $this->anchor[1] = max(0, min($this->lineLength($this->anchor[0]), (int)$this->anchor[1]));
  }

  public function setPosition(int $row, int $column, bool $select = false): void {
    $this->caret = [$row, $column];
    $this->clamp();
    $this->preferredColumn = $this->caret[1];
    if (!$select) {
      $this->collapse();
    }
  }

  public function selectAll(): void {
    $lastRow = max(0, count($this->lines) - 1);
    $this->anchor = [0, 0];
    $this->caret = [$lastRow, $this->lineLength($lastRow)];
    $this->preferredColumn = $this->caret[1];
  }

  public function collapse(): void {
    $this->anchor = $this->caret;
  }

  public function hasSelection(): bool {
    return $this->caret !== $this->anchor;
  }

  public function range(): array {
    [$row1, $col1] = $this->caret;
    [$row2, $col2] = $this->anchor;
    if ($row1 < $row2 || ($row1 === $row2 && $col1 <= $col2)) {
      return [$row1, $col1, $row2, $col2];
    }
    return [$row2, $col2, $row1, $col1];
  }

  public function selectedText(): string {
    if (!$this->hasSelection()) {
      [$row, $col] = $this->caret;
      return $this->charAt($row, $col);
    }
    [$row1, $col1, $row2, $col2] = $this->range();
    $selected = [];
    for ($row = $row1; $row <= $row2; $row++) {
      $line = $this->lines[$row] ?? '';
      if ($row === $row1 && $row === $row2) {
        $selected[] = mb_substr($line, $col1, $col2 - $col1);
      } else if ($row === $row1) {
        $selected[] = mb_substr($line, $col1);
      } else if ($row === $row2) {
        $selected[] = mb_substr($line, 0, $col2);
      } else {
        $selected[] = $line;
      }
    }
    $text = implode("\n", $selected);
    if ($this->caret === [$row2, $col2]) {
      $text .= $this->charAt($row2, $col2);
    }
    return $text;
  }

  public function moveLeft(bool $select = false): void {
    if (!$select && $this->hasSelection()) {
      [$row, $col] = $this->range();
      $this->setPosition($row, $col);
      return;
    }
    if ($this->caret[1] > 0) {
      $this->caret[1]--;
    } else if ($this->caret[0] > 0) {
      $this->caret[0]--;
      $this->caret[1] = $this->lineLength($this->caret[0]);
    }
    $this->afterHorizontalMove($select);
  }

  public function moveRight(bool $select = false): void {
    if (!$select && $this->hasSelection()) {
      [, , $row, $col] = $this->range();
      $this->setPosition($row, $col);
      return;
    }
    $length = $this->lineLength($this->caret[0]);
    if ($this->caret[1] < $length) {
      $this->caret[1]++;
    } else if ($this->caret[0] < count($this->lines) - 1) {
      $this->caret[0]++;
      $this->caret[1] = 0;
    }
    $this->afterHorizontalMove($select);
  }

  public function moveUp(bool $select = false, int $rows = 1): void {
    $this->moveVertical(-$rows, $select);
  }

  public function moveDown(bool $select = false, int $rows = 1): void {
    $this->moveVertical($rows, $select);
  }

  public function moveLineStart(bool $select = false): void {
    $this->caret[1] = 0;
    $this->afterHorizontalMove($select);
  }

  public function moveLineEnd(bool $select = false): void {
    $this->caret[1] = $this->lineLength($this->caret[0]);
    $this->afterHorizontalMove($select);
  }

  public function moveDocumentStart(bool $select = false): void {
    $this->caret = [0, 0];
    $this->afterHorizontalMove($select);
  }

  public function moveDocumentEnd(bool $select = false): void {
    $row = max(0, count($this->lines) - 1);
    $this->caret = [$row, $this->lineLength($row)];
    $this->afterHorizontalMove($select);
  }

  protected function moveVertical(int $rows, bool $select): void {
    if ($this->preferredColumn === false) {
      $this->preferredColumn = $this->caret[1];
    }
    $this->caret[0] += $rows;
    $this->caret[0] = max(0, min(count($this->lines) - 1, $this->caret[0]));
    $this->caret[1] = min($this->lineLength($this->caret[0]), $this->preferredColumn);
    if (!$select) {
      $this->collapse();
    }
  }

  protected function afterHorizontalMove(bool $select): void {
    $this->clamp();
    $this->preferredColumn = $this->caret[1];
    if (!$select) {
      $this->collapse();
    }
  }

  protected function clamp(): void {
    if (empty($this->lines)) {
      $this->lines = [''];
    }
    $this->caret[0] = max(0, min(count($this->lines) - 1, (int)$this->caret[0]));
    $this->caret[1] = max(0, min($this->lineLength($this->caret[0]), (int)$this->caret[1]));
  }

  protected function lineLength(int $row): int {
    return mb_strlen($this->lines[$row] ?? '');
  }

  protected function charAt(int $row, int $col): string {
    $line = $this->lines[$row] ?? '';
    if ($col < mb_strlen($line)) {
      return mb_substr($line, $col, 1);
    }
    return $row < count($this->lines) - 1 ? "\n" : '';
  }

}
