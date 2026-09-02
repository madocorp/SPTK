<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\RenderTarget;

/**
 * Renders static text wrapped to the available frame width.
 */
class TextBlock extends Element {

  public function __construct(string $name = '', protected string $text = '') {
    parent::__construct($name);
    $this->setPreferredColumns(mb_strlen($this->text));
  }

  public function setText(string $text): static {
    if ($this->text !== $text) {
      $this->invalidateLayout();
    }
    $this->text = $text;
    $this->setPreferredColumns(mb_strlen($this->text));
    return $this;
  }

  public function text(): string {
    return $this->text;
  }

  public function preferredRowsForColumns(int $columns): int {
    return count($this->wrappedLines($columns));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    $lines = $this->wrappedLines($this->frame->width);
    $overflow = count($lines) > $this->frame->height;
    $contentRows = $overflow ? max(0, $this->frame->height - 1) : $this->frame->height;
    foreach (array_slice($lines, 0, $contentRows) as $row => $line) {
      $target->write($this->frame->x, $this->frame->y + $row, mb_substr($line, 0, $this->frame->width), $this->theme->fg, $this->theme->bg);
    }
    if ($overflow && $this->frame->width > 0 && $this->frame->height > 0) {
      $marker = mb_substr('--- vvv ---', 0, $this->frame->width);
      $x = $this->frame->x + max(0, intdiv($this->frame->width - mb_strlen($marker), 2));
      $target->write($x, $this->frame->bottom() - 1, $marker, $this->theme->markerFg, $this->theme->bg);
    }
  }

  protected function wrappedLines(int $columns): array {
    $columns = max(1, $columns);
    $lines = [];
    foreach (preg_split('/\R/u', $this->text) ?: [''] as $paragraph) {
      $this->wrapParagraph((string)$paragraph, $columns, $lines);
    }
    return $lines === [] ? [''] : $lines;
  }

  protected function wrapParagraph(string $paragraph, int $columns, array &$lines): void {
    $text = trim($paragraph);
    if ($text === '') {
      $lines[] = '';
      return;
    }
    while (mb_strlen($text) > $columns) {
      $chunk = mb_substr($text, 0, $columns + 1);
      $break = mb_strrpos($chunk, ' ');
      if ($break === false || $break <= 0) {
        $break = $columns;
      }
      $lines[] = rtrim(mb_substr($text, 0, $break));
      $text = ltrim(mb_substr($text, $break));
    }
    $lines[] = $text;
  }

}
