<?php

namespace SPTK\Widgets\ProgressBar;

use SPTK\Core\{Cell, Color, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Paints a determinate bar with an optional title and centered progress label. */
final class ProgressBar extends Widget {

  use WidgetEventEmitter;

  private float $value = 0;
  private float $maximum = 100;
  private string $display = 'percent';
  private string $text = '';
  private string $title = '';

  /** Initialize progress, label mode, title, and inherited colors. */
  public function __construct(float $value = 0, float $maximum = 100, string $display = 'percent', string $text = '', string $title = '', private Style $style = new Style()) {
    $this->setProgress($value, $maximum);
    $this->setTitle($title);
    $this->text = $this->oneLine($text);
    $this->setDisplay($display);
  }

  /** Return the clamped current value. */
  public function value(): float {
    return $this->value;
  }

  /** Return the current value using the getter used by other widgets. */
  public function getValue(): float {
    return $this->value;
  }

  /** Return the current maximum. */
  public function maximum(): float {
    return $this->maximum;
  }

  /** Return the selected label mode. */
  public function display(): string {
    return $this->display;
  }

  /** Return the custom label text. */
  public function text(): string {
    return $this->text;
  }

  /** Return the title above the bar. */
  public function title(): string {
    return $this->title;
  }

  /** Set the current value while retaining the maximum. */
  public function setValue(float $value): void {
    $this->setProgress($value, $this->maximum);
  }

  /** Set the maximum and clamp the current value if necessary. */
  public function setMaximum(float $maximum): void {
    $this->setProgress($this->value, $maximum);
  }

  /** Clamp finite progress to the interval from zero to maximum. */
  public function setProgress(float $value, float $maximum): void {
    if (!is_finite($value) || !is_finite($maximum) || $maximum < 0) {
      throw new \InvalidArgumentException('ProgressBar requires finite progress and a nonnegative maximum.');
    }
    $this->maximum = $maximum;
    $this->value = max(0, min($maximum, $value));
  }

  /** Select percent, fraction, or custom text for the centered bar label. */
  public function setDisplay(string $display): void {
    if (!in_array($display, ['percent', 'fraction', 'text'], true)) {
      throw new \InvalidArgumentException('ProgressBar display must be percent, fraction, or text.');
    }
    $this->display = $display;
  }

  /** Set custom label text and switch to text display mode. */
  public function setText(string $text): void {
    $this->text = $this->oneLine($text);
    $this->display = 'text';
  }

  /** Set the optional title shown above the bar. */
  public function setTitle(string $title): void {
    $this->title = $this->oneLine($title);
  }

  /** Return completed progress as a fraction from zero to one. */
  public function ratio(): float {
    return $this->maximum > 0 ? $this->value / $this->maximum : 0;
  }

  /** Format the centered label for the selected display mode. */
  public function label(): string {
    return match ($this->display) {
      'fraction' => $this->value . ' / ' . $this->maximum,
      'text' => $this->text,
      default => (string)round($this->ratio() * 100) . '%',
    };
  }

  /** Suggest enough columns for the title or progress label. */
  public function preferredWidth(): ?int {
    return max(20, TextMetrics::width($this->title), TextMetrics::width($this->label()));
  }

  /** Reserve a second row when a title is present. */
  public function preferredHeight(): ?int {
    return $this->title === '' ? 1 : 2;
  }

  /** Return the unfilled bar color used behind the widget tile. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint the title, filled cells, and a centered label. */
  public function paint(GridWriter $writer): void {
    $width = $writer->width();
    $height = $writer->height();
    if ($width < 1 || $height < 1) {
      return;
    }
    $writer->fill($this->style->foreground, $this->style->background);
    $barRow = $this->title !== '' && $height > 1 ? 1 : 0;
    if ($barRow === 1) {
      $writer->write(0, 0, $this->title, $this->style->highlight, $this->style->background);
    }
    $filled = (int)floor($width * $this->ratio());
    $on = new Cell(' ', $this->style->background, $this->style->selected);
    $off = new Cell(' ', $this->style->foreground, $this->style->background);
    for ($x = 0; $x < $width; $x++) {
      $writer->put($x, $barRow, $x < $filled ? $on : $off);
    }
    $label = $this->label();
    $labelWidth = min($width, TextMetrics::width($label));
    $left = intdiv($width - $labelWidth, 2);
    foreach (TextMetrics::cells($label, 0, $labelWidth) as $offset => [$glyph, $span]) {
      if ($span === 0) {
        continue;
      }
      $x = $left + $offset;
      $writer->put($x, $barRow, new Cell($glyph, $x < $filled ? $this->style->background : $this->style->foreground, $x < $filled ? $this->style->selected : $this->style->background, $span));
    }
  }

  /** Keep the read-only bar out of input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Ignore input; progress is changed programmatically. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Replace line breaks and tabs, then reject other control characters. */
  private function oneLine(string $text): string {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('ProgressBar text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
    if (preg_match('/[\x00-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('ProgressBar text must be printable.');
    }
    return $text;
  }

}
