<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Place;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Stacks children vertically using each child's preferred row height.
 */
class Flow extends Element {

  protected array $rows = [];
  protected array $rowLengths = [];

  public function add(Element $child): static {
    return $this->place($child);
  }

  public function place(Element $child, int|Place|null $rows = null): static {
    parent::add($child);
    if ($rows instanceof Place) {
      if ($rows->mode() !== 'cursor') {
        throw new \InvalidArgumentException('Flow accepts only cursor placement.');
      }
      $height = $rows->height();
      if ($height !== null) {
        $this->rowLengths[spl_object_id($child)] = $height;
      }
    } else if ($rows !== null) {
      $this->rows[spl_object_id($child)] = max(1, $rows);
    }
    return $this;
  }

  public function spacer(int $rows = 1): static {
    return $this->place(new Label('', ''), $rows);
  }

  public function preferredRows(): int {
    if ($this->frame->width > 0) {
      return $this->preferredRowsForColumns($this->frame->width);
    }
    $rows = 0;
    $previous = null;
    foreach ($this->children as $child) {
      if ($previous !== null) {
        $rows += $this->gapBefore($previous, $child);
      }
      $rows += max(1, $this->rows[spl_object_id($child)] ?? $child->preferredRows());
      $previous = $child;
    }
    return max(1, $rows);
  }

  public function preferredRowsForColumns(int $columns): int {
    $rows = 0;
    $previous = null;
    foreach ($this->children as $child) {
      if ($previous !== null) {
        $rows += $this->gapBefore($previous, $child);
      }
      $rows += $this->childRows($child, $columns);
      $previous = $child;
    }
    return max(1, $rows);
  }

  public function layout(): void {
    $y = $this->frame->y;
    $previous = null;
    foreach ($this->children as $child) {
      if ($previous !== null) {
        $y += min($this->gapBefore($previous, $child), max(0, $this->frame->bottom() - $y));
      }
      $availableRows = max(0, $this->frame->bottom() - $y);
      $rows = min($this->layoutChildRows($child, $this->frame->width, $availableRows), $availableRows);
      $child->setFrame(new Rect($this->frame->x, $y, $this->frame->width, $rows));
      $y += $rows;
      $previous = $child;
    }
  }

  protected function childRows(Element $child, int $columns): int {
    $id = spl_object_id($child);
    if (isset($this->rows[$id])) {
      return max(1, $this->rows[$id]);
    }
    if (isset($this->rowLengths[$id])) {
      return max(1, $this->rowLengths[$id]->resolveCells(PHP_INT_MAX, 1, $child->preferredRowsForColumns($columns)));
    }
    return max(1, $child->preferredRowsForColumns($columns));
  }

  protected function layoutChildRows(Element $child, int $columns, int $availableRows): int {
    $id = spl_object_id($child);
    if (isset($this->rows[$id])) {
      return max(1, $this->rows[$id]);
    }
    if (isset($this->rowLengths[$id])) {
      return max(1, $this->rowLengths[$id]->resolveCells($availableRows, 1, $child->preferredRowsForColumns($columns)));
    }
    return max(1, $child->preferredRowsForColumns($columns));
  }

  protected function gapBefore(Element $previous, Element $child): int {
    if ($previous instanceof Label) {
      return 0;
    }
    if ($previous instanceof Checkbox && $child instanceof Checkbox) {
      return 0;
    }
    return 1;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

}
