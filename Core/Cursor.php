<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;

/** Tracks a grapheme caret through text lines and preserves its display column vertically. */
final class Cursor {

  private array $position = [0, 0];
  private int|false $preferredColumn = false;

  /** Track a grapheme position in the supplied text lines. */
  public function __construct(private array &$lines) {
    if ($lines === []) {
      $lines = [''];
    }
  }

  /** Return the current row and grapheme index. */
  public function position(): array {
    return $this->position;
  }

  /** Set the cursor to a valid text position. */
  public function setPosition(int $row, int $column): void {
    $row = max(0, min(count($this->lines) - 1, $row));
    $this->position = [$row, max(0, min(TextMetrics::length($this->lines[$row]), $column))];
    $this->preferredColumn = false;
  }

  /** Move one grapheme left, crossing a line break when needed. */
  public function moveLeft(): void {
    [$row, $column] = $this->position;
    if ($column > 0) {
      $column--;
    } else if ($row > 0) {
      $row--;
      $column = TextMetrics::length($this->lines[$row]);
    }
    $this->setPosition($row, $column);
  }

  /** Move one grapheme right, crossing a line break when needed. */
  public function moveRight(): void {
    [$row, $column] = $this->position;
    if ($column < TextMetrics::length($this->lines[$row])) {
      $column++;
    } else if ($row < count($this->lines) - 1) {
      $row++;
      $column = 0;
    }
    $this->setPosition($row, $column);
  }

  /** Move vertically while retaining the preferred display column. */
  public function moveVertical(int $distance): void {
    [$row, $column] = $this->position;
    if ($this->preferredColumn === false) {
      $this->preferredColumn = TextMetrics::width(TextMetrics::slice($this->lines[$row], 0, $column));
    }
    $row = max(0, min(count($this->lines) - 1, $row + $distance));
    $column = TextMetrics::length($this->lines[$row]);
    while ($column > 0 && TextMetrics::width(TextMetrics::slice($this->lines[$row], 0, $column)) > $this->preferredColumn) {
      $column--;
    }
    $this->position = [$row, $column];
  }

}
