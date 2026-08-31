<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Place;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Places a group of children on one row.
 */
class FlowRow extends Element {

  protected array $columns = [];
  protected array $columnLengths = [];

  public function __construct(string $name = '', protected string $align = 'left', protected int $gap = 2) {
    parent::__construct($name);
  }

  public function setAlign(string $align): static {
    $this->align = $align;
    $this->invalidateLayout();
    return $this;
  }

  public function setGap(int $gap): static {
    $this->gap = max(0, $gap);
    $this->invalidateLayout();
    return $this;
  }

  public function add(Element $child): static {
    return $this->place($child);
  }

  public function place(Element $child, int|Place|null $columns = null): static {
    parent::add($child);
    if ($columns instanceof Place) {
      if ($columns->mode() !== 'cursor') {
        throw new \InvalidArgumentException('FlowRow accepts only cursor placement.');
      }
      $width = $columns->width();
      if ($width !== null) {
        $this->columnLengths[spl_object_id($child)] = $width;
      }
    } else if ($columns !== null) {
      $this->columns[spl_object_id($child)] = max(1, $columns);
    }
    return $this;
  }

  public function preferredColumns(): int {
    $width = 0;
    foreach ($this->children as $i => $child) {
      $width += $this->childColumns($child);
      if ($i > 0) {
        $width += $this->gap;
      }
    }
    return $width;
  }

  public function preferredRows(): int {
    return $this->preferredRowsForColumns($this->preferredColumns());
  }

  public function preferredRowsForColumns(int $columns): int {
    $rows = 1;
    foreach ($this->children as $child) {
      $rows = max($rows, $this->childRows($child));
    }
    return $rows;
  }

  public function layout(): void {
    $count = count($this->children);
    if ($count === 0) {
      return;
    }
    if ($this->align === 'justify' && $count > 1) {
      $this->layoutJustified();
      return;
    }
    $widths = [];
    $total = 0;
    foreach ($this->children as $child) {
      $width = $this->childColumns($child);
      $widths[] = $width;
      $total += $width;
    }
    $total += $this->gap * max(0, $count - 1);
    $x = match ($this->align) {
      'center' => $this->frame->x + max(0, intdiv($this->frame->width - $total, 2)),
      'right' => $this->frame->x + max(0, $this->frame->width - $total),
      default => $this->frame->x,
    };
    foreach ($this->children as $i => $child) {
      $width = min($widths[$i], max(0, $this->frame->right() - $x));
      $child->setFrame(new Rect($x, $this->frame->y, $width, min($this->frame->height, $this->childRowsForColumns($child, $width))));
      $x += $width + $this->gap;
    }
  }

  protected function layoutJustified(): void {
    $count = count($this->children);
    $widths = [];
    $total = 0;
    foreach ($this->children as $child) {
      $width = $this->childColumns($child);
      $widths[] = $width;
      $total += $width;
    }
    $gap = $count > 1 ? max($this->gap, intdiv(max(0, $this->frame->width - $total), $count - 1)) : 0;
    $x = $this->frame->x;
    foreach ($this->children as $i => $child) {
      $width = min($widths[$i], max(0, $this->frame->right() - $x));
      $child->setFrame(new Rect($x, $this->frame->y, $width, min($this->frame->height, $this->childRowsForColumns($child, $width))));
      $x += $width + $gap;
    }
  }

  protected function childColumns(Element $child): int {
    $id = spl_object_id($child);
    if (isset($this->columns[$id])) {
      return max(1, $this->columns[$id]);
    }
    if (isset($this->columnLengths[$id])) {
      return max(1, $this->columnLengths[$id]->resolveCells(PHP_INT_MAX, 1, $child->preferredColumns() ?: 1));
    }
    return max(1, $child->preferredColumns() ?: 1);
  }

  protected function childRows(Element $child): int {
    return $this->childRowsForColumns($child, $this->childColumns($child));
  }

  protected function childRowsForColumns(Element $child, int $columns): int {
    return max(1, $child->preferredRowsForColumns(max(1, $columns)));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

}
