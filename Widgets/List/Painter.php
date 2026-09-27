<?php

namespace SPTK\Widgets\List;

use SPTK\Core\{Color, ItemViewport, ScrollIndicator};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints list rows, matched prefixes, and scroll indicators. */
final class Painter {

  /** Hold the colors used by list rows, matches, and the cursor. */
  public function __construct(private readonly Color $fg, private readonly Color $bg, private readonly Color $cursorBg, private readonly Color $highlight, private readonly Color $selected) {
  }

  /** Paint the visible list viewport. */
  public function paint(GridWriter $writer, array $items, array $visible, ItemViewport $viewport, string $query, bool $active, bool $multiple): void {
    $writer->fill($this->fg, $this->bg);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return;
    }
    if ($visible === []) {
      $writer->write(0, 0, $query === '' ? '(empty)' : "(no results for '{$query}')", $this->highlight, $this->bg);
      return;
    }
    $scroll = $viewport->scroll();
    for ($y = 0; $y < $writer->height(); $y++) {
      $index = $visible[$scroll + $y] ?? null;
      if ($index === null) {
        continue;
      }
      $item = $items[$index];
      $selected = $multiple ? $item['selected'] : $scroll + $y === $viewport->position();
      $cursorBg = $active && $scroll + $y === $viewport->position() ? $this->cursorBg : $this->bg;
      $writer->write(0, $y, $item['label'], $selected ? $this->selected : $this->fg, $cursorBg);
      $this->paintMatch($writer, $item['label'], $query, $y, $cursorBg);
    }
    $this->indicator($writer, ScrollIndicator::label($scroll, $writer->height(), '▲'), 0);
    $below = max(0, count($visible) - $scroll - $writer->height());
    $this->indicator($writer, ScrollIndicator::label($below, $writer->height(), '▼'), $writer->height() - 1);
  }

  /** Overlay a matching prefix in the highlight color. */
  private function paintMatch(GridWriter $writer, string $label, string $query, int $y, Color $bg): void {
    if ($query === '' || !str_starts_with(mb_strtolower($label), mb_strtolower($query))) {
      return;
    }
    $writer->write(0, $y, mb_substr($label, 0, mb_strlen($query)), $this->highlight, $bg);
  }

  /** Draw an inverted scroll mark against the right edge. */
  private function indicator(GridWriter $writer, string $label, int $y): void {
    if ($label === '') {
      return;
    }
    if (TextMetrics::width($label) > $writer->width()) {
      $label = preg_replace('/[0-9]/', '', $label);
    }
    if (TextMetrics::width($label) <= $writer->width()) {
      $writer->write($writer->width() - TextMetrics::width($label), $y, $label, $this->bg, $this->highlight);
    }
  }

}
