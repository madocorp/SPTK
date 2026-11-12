<?php

namespace SPTK\Widgets\Slider;

use SPTK\Core\{Cell, Color, Style, Widget, WidgetTitle};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Lets a user change a numeric value with an optional title and filled bar. */
final class Slider extends Widget {

  use WidgetEventEmitter;

  private float $value;
  private bool $active = false;
  private bool $muted = false;
  private WidgetTitle $title;

  /** Set the range, normal and small steps, text, and inherited colors. */
  public function __construct(float $value = 0, private float $minimum = 0, private float $maximum = 100, private float $step = 10, private string $label = '', private string $unit = '%', private Style $style = new Style(), ?string $title = null, private float $smallStep = 1) {
    if (!is_finite($minimum) || !is_finite($maximum) || $maximum <= $minimum
      || !is_finite($step) || $step <= 0 || !is_finite($smallStep) || $smallStep <= 0) {
      throw new \InvalidArgumentException('Slider requires a finite increasing range and positive steps.');
    }
    $this->setLabel($label);
    $this->setUnit($unit);
    $this->title = new WidgetTitle($title, $style);
    $this->setValue($value);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Return the current value. */
  public function getValue(): float {
    return $this->value;
  }

  /** Return the current value using the concise widget getter. */
  public function value(): float {
    return $this->value;
  }

  /** Report whether this slider is visually muted. */
  public function isMuted(): bool {
    return $this->muted;
  }

  /** Set a value without emitting a user change event. */
  public function setValue(float $value): void {
    if (!is_finite($value)) {
      throw new \InvalidArgumentException('Slider value must be finite.');
    }
    $this->value = max($this->minimum, min($this->maximum, $value));
  }

  /** Show or clear muted colors without changing the numeric value. */
  public function setMuted(bool $muted): void {
    $this->muted = $muted;
  }

  /** Replace the text before the bar. */
  public function setLabel(string $label): void {
    $this->label = $this->oneLine($label);
  }

  /** Replace the suffix after the numeric value. */
  public function setUnit(string $unit): void {
    $this->unit = $this->oneLine($unit);
  }

  /** Set or remove the heading above the control. */
  public function setTitle(?string $title): void {
    $before = $this->title->height();
    $this->title = new WidgetTitle($title, $this->style);
    if ($this->title->height() !== $before) {
      $this->emit('preferredSizeChange');
    }
  }

  /** Reserve a control row and an optional title row. */
  public function preferredHeight(): ?int {
    return 1 + $this->title->height();
  }

  /** Return the inherited tile background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint an optional title, normal-color label, filled bar, and value. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    $mutedColor = new Color(150, 150, 150);
    $ink = $this->muted ? $mutedColor : $this->style->foreground;
    $fill = $this->muted ? $mutedColor : $this->style->selected;
    $writer = $this->title->body($writer, color: $this->muted ? $mutedColor : null);
    $width = $writer->width();
    if ($width < 1 || $writer->height() < 1) {
      return;
    }
    $value = sprintf('%g', $this->value) . $this->unit;
    $prefix = $this->label === '' ? '' : $this->label . ' ';
    if (TextMetrics::width($prefix . ' ' . $value) + 1 > $width) {
      $prefix = '';
    }
    $barWidth = $width - TextMetrics::width($prefix . ' ' . $value);
    if ($barWidth < 1) {
      $writer->write(0, 0, $value, $this->muted ? $mutedColor : ($this->active ? $this->style->selected : $ink), $this->style->background);
      return;
    }
    $barX = TextMetrics::width($prefix);
    $writer->write(0, 0, $prefix, $ink, $this->style->background);
    $filled = (int)round($barWidth * ($this->value - $this->minimum) / ($this->maximum - $this->minimum));
    $track = $this->style->background->darkened(0.85);
    for ($x = 0; $x < $barWidth; $x++) {
      $writer->put($barX + $x, 0, $x < $filled
        ? new Cell(' ', $this->style->background, $fill)
        : new Cell(' ', $ink, $track));
    }
    $writer->write($barX + $barWidth + 1, 0, $value, $this->muted ? $mutedColor : ($this->active ? $this->style->selected : $ink), $this->style->background);
  }

  /** Move vertically by the normal step, horizontally by the small step. */
  public function handleInput(mixed $event): bool {
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $modifiers = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $modifiers);
    $next = match ($key) {
      SDL::KEY_LEFT => $this->value - $this->smallStep,
      SDL::KEY_RIGHT => $this->value + $this->smallStep,
      SDL::KEY_DOWN => $this->value - $this->step,
      SDL::KEY_UP => $this->value + $this->step,
      SDL::KEY_HOME => $this->minimum,
      SDL::KEY_END => $this->maximum,
      default => null,
    };
    if ($next === null) {
      return false;
    }
    $before = $this->value;
    $this->setValue($next);
    if ($this->value !== $before) {
      $this->emit('change');
    }
    return true;
  }

  /** Explain how to adjust a selected slider. */
  protected function defaultTip(bool $active): string {
    return $active ? 'Up/Down change by the normal step; Left/Right use the small step; Home/End choose a limit; Return or Esc finishes.' : 'Return adjusts this slider.';
  }

  /** Record activation for the value highlight. */
  private function activate(): void {
    $this->active = true;
  }

  /** Remove the active value highlight. */
  private function deactivate(): void {
    $this->active = false;
  }

  /** Keep display text on one printable line. */
  private function oneLine(string $text): string {
    if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Slider text must be printable single-line UTF-8.');
    }
    return $text;
  }

}
