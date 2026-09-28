<?php

namespace SPTK\Widgets\Input;

use SPTK\Core\{ScrollIndicator, Style, TextCursor};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints a one-row editor with a block caret and horizontal page indicators. */
final class Painter {

  /** Create the input painter with inherited colors. */
  public function __construct(private readonly Style $style) {
  }

  /** Paint text and return the actual horizontal scroll and text viewport width. */
  public function paint(GridWriter $writer, string $text, TextCursor $cursor, int $scroll, bool $active, int $tabSize): array {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return [$scroll, 1];
    }
    $caret = TextMetrics::column($text, $cursor->position()[1], $tabSize);
    [$left, $right, $width, $scroll] = $this->layout($text, $caret, $writer->width(), $scroll, $active, $tabSize);
    $x0 = TextMetrics::length($left);
    $selection = $cursor->hasSelection() && $active ? $cursor->selectionRange() : null;
    foreach (TextMetrics::cells($text, $scroll, $width, $tabSize) as $x => [$glyph, $span, $index]) {
      if ($span === 0) {
        continue;
      }
      $selected = $selection !== null && $index < TextMetrics::length($text)
        && $index >= $selection[1] && $index < $selection[3];
      $writer->set($x0 + $x, 0, $glyph, $this->style->foreground, $selected ? $this->style->cursorBackground : $this->style->background);
    }
    $writer->write(0, 0, $left, $this->style->background, $this->style->highlight);
    $writer->write($writer->width() - TextMetrics::length($right), 0, $right, $this->style->background, $this->style->highlight);
    if ($active) {
      $x = $x0 + $caret - $scroll;
      $glyph = TextMetrics::slice($text, $cursor->position()[1], 1);
      if ($glyph !== "\t" && preg_match('/^[\p{Cc}\p{Cf}]/u', $glyph)) {
        $glyph = '�';
      }
      if ($glyph === '' || $glyph === "\t" || $x + TextMetrics::glyphWidth($glyph) > $x0 + $width) {
        $glyph = ' ';
      }
      if ($x >= $x0 && $x < $x0 + $width) {
        $writer->set($x, 0, $glyph, $this->style->foreground, $this->style->cursorBackground);
      }
    }
    return [$scroll, $width];
  }

  /** Fit indicator labels and a visible caret into the tile width. */
  private function layout(string $text, int $caret, int $columns, int $scroll, bool $active, int $tabSize): array {
    $length = TextMetrics::width($text, $tabSize);
    $extent = $length + ($active ? 1 : 0);
    foreach ([true, false] as $numbers) {
      for ($width = $columns; $width >= 1; $width--) {
        $offset = min($scroll, max(0, $extent - $width));
        if ($active) {
          $offset = max(0, min($offset, $caret));
          $offset = max($offset, $caret - $width + 1);
        }
        $left = $this->indicator($offset, $width, true, $numbers);
        $right = $this->indicator(max(0, $length - $offset - $width), $width, false, $numbers);
        if ($width + TextMetrics::length($left) + TextMetrics::length($right) === $columns) {
          return [$left, $right, $width, $offset];
        }
      }
    }
    return ['', '', $columns, max(0, $caret - $columns + 1)];
  }

  /** Format one hidden-content arrow with an optional page count. */
  private function indicator(int $hidden, int $width, bool $left, bool $numbers): string {
    if ($hidden <= 0) {
      return '';
    }
    $arrow = $left ? '◀' : '▶';
    return $numbers ? ScrollIndicator::label($hidden, $width, $arrow) : $arrow;
  }

}
