<?php

namespace SPTK\Widgets\Title;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Displays a single-line heading in the inherited highlight color. */
final class Title extends Widget {

  use WidgetEventEmitter;

  private string $text = '';

  /** Create a heading that can be changed after XML parsing. */
  public function __construct(string $text = '', private readonly Style $style = new Style()) {
    $this->setText($text);
  }

  /** Return the current heading. */
  public function text(): string {
    return $this->text;
  }

  /** Replace the heading with printable one-line UTF-8 text. */
  public function setText(string $text): void {
    if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Title requires printable one-line UTF-8 text.');
    }
    $this->text = $text;
    $this->emit('change');
  }

  /** Use the inherited background around the heading. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint one highlighted row and clear any unused space. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->highlight, $this->style->background);
    if ($writer->height() > 0 && $writer->width() > 0) {
      $writer->write(0, 0, $this->text, $this->style->highlight, $this->style->background);
    }
  }

  /** Let arrow keys leave the title tile without entering input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Titles do not handle direct input. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Measure the heading's natural width in grid cells. */
  public function preferredWidth(): ?int {
    return TextMetrics::width($this->text);
  }

  /** Titles occupy exactly one grid row by default. */
  public function preferredHeight(): ?int {
    return 1;
  }

  /** Explain the role of a selected title tile. */
  protected function defaultTip(bool $active): string {
    return 'Screen title; arrow keys move between tiles.';
  }

}
