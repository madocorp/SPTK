<?php

namespace SPTK\Widgets\List;

use SPTK\Core\{Color, ItemViewport, ScrollIndicator, Style};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints list rows, matched prefixes, and scroll indicators. */
final class Painter {

  /** Hold the colors used by list rows, matches, and the cursor. */
  public function __construct(private readonly Style $style) {
  }

  /** Paint the visible list viewport. */
  public function paint(GridWriter $writer, array $items, array $visible, ItemViewport $viewport, string $query, bool $active, bool $multiple): void {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return;
    }
    if ($visible === []) {
      $writer->write(0, 0, $query === '' ? '(empty)' : "(no results for '{$query}')", $this->style->highlight, $this->style->background);
      return;
    }
    $scroll = $viewport->scroll();
    for ($y = 0; $y < $writer->height(); $y++) {
      $this->paintItem($writer, $items, $visible, $viewport, $query, $active, $multiple, $y);
    }
    $this->indicator($writer, ScrollIndicator::label($scroll, $writer->height(), '▲'), 0);
    $below = max(0, count($visible) - $scroll - $writer->height());
    $this->indicator($writer, ScrollIndicator::label($below, $writer->height(), '▼'), $writer->height() - 1);
  }

  /** Repaint selected local rows and any scroll marks they overlap. */
  public function paintRows(GridWriter $writer, array $items, array $visible, ItemViewport $viewport, string $query, bool $active, bool $multiple, array $rows): void {
    foreach ($rows as $y) {
      $writer->fillRow($y, $this->style->foreground, $this->style->background);
      $this->paintItem($writer, $items, $visible, $viewport, $query, $active, $multiple, $y);
      if ($y === 0) {
        $this->indicator($writer, ScrollIndicator::label($viewport->scroll(), $writer->height(), '▲'), 0);
      }
      if ($y === $writer->height() - 1) {
        $below = max(0, count($visible) - $viewport->scroll() - $writer->height());
        $this->indicator($writer, ScrollIndicator::label($below, $writer->height(), '▼'), $y);
      }
    }
  }

  /** Paint one visible item with its cursor and query colors. */
  private function paintItem(GridWriter $writer, array $items, array $visible, ItemViewport $viewport, string $query, bool $active, bool $multiple, int $y): void {
    $position = $viewport->scroll() + $y;
    $index = $visible[$position] ?? null;
    if ($index === null) {
      return;
    }
    $item = $items[$index];
    $selected = $multiple ? $item['selected'] : $position === $viewport->position();
    $cursorBg = $active && $position === $viewport->position() ? $this->style->cursorBackground : $this->style->background;
    $writer->write(0, $y, $item['label'], $selected ? $this->style->selected : $this->style->foreground, $cursorBg);
    $this->paintMatch($writer, $item, $query, $y, $cursorBg);
  }

  /** Overlay a matching prefix in the highlight color. */
  private function paintMatch(GridWriter $writer, array $item, string $query, int $y, Color $bg): void {
    $offset = $item['searchOffset'] ?? 0;
    $label = mb_substr($item['label'], $offset);
    if ($query === '' || !str_starts_with(mb_strtolower($label), mb_strtolower($query))) {
      return;
    }
    $writer->write($offset, $y, mb_substr($label, 0, mb_strlen($query)), $this->style->highlight, $bg);
  }

  /** Draw an inverted scroll mark against the right edge. */
  private function indicator(GridWriter $writer, string $label, int $y): void {
    $label = ScrollIndicator::fit($label, $writer->width(), true);
    if ($label !== '') {
      $writer->write($writer->width() - TextMetrics::width($label), $y, $label, $this->style->background, $this->style->highlight);
    }
  }

}
