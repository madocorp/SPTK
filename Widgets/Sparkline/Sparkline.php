<?php

namespace SPTK\Widgets\Sparkline;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\{GridWriter, Sparkline as SparklineRenderer};

/** Displays caller-provided numeric samples as a read-only one-row chart. */
final class Sparkline extends Widget {

  use WidgetEventEmitter;

  private array $samples = [];
  private float $maximum = 100;
  private string $label = '';
  private bool $showValue = false;
  private string $unit = '';

  /** Initialize samples, display attributes, and inherited colors. */
  public function __construct(array $samples = [], float $maximum = 100, private Style $style = new Style(), string $label = '', bool $showValue = false, string $unit = '') {
    $this->setMaximum($maximum);
    $this->setSamples($samples);
    $this->setLabel($label);
    $this->setShowValue($showValue);
    $this->setUnit($unit);
  }

  /** Replace chart values without retaining a history in the widget. */
  public function setSamples(array $samples): void {
    $values = [];
    foreach ($samples as $sample) {
      if (!is_int($sample) && !is_float($sample) || !is_finite((float)$sample)) {
        throw new \InvalidArgumentException('Sparkline samples must be finite numbers.');
      }
      $values[] = (float)$sample;
    }
    $this->samples = $values;
  }

  /** Set the positive value represented by the tallest block. */
  public function setMaximum(float $maximum): void {
    if (!is_finite($maximum) || $maximum <= 0) {
      throw new \InvalidArgumentException('Sparkline maximum must be positive and finite.');
    }
    $this->maximum = $maximum;
  }

  /** Return the current samples for inspection or incremental updates. */
  public function samples(): array {
    return $this->samples;
  }

  /** Set the fixed text shown before the chart. */
  public function setLabel(string $label): void {
    $this->label = $this->oneLine($label);
  }

  /** Choose whether to show the newest numeric value. */
  public function setShowValue(bool $showValue): void {
    $this->showValue = $showValue;
  }

  /** Set the suffix shown after a visible numeric value. */
  public function setUnit(string $unit): void {
    $this->unit = $this->oneLine($unit);
  }

  /** Reserve one grid row for the chart. */
  public function preferredHeight(): ?int {
    return 1;
  }

  /** Use the inherited tile background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Draw the newest samples that fit the assigned width. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($writer->height() > 0) {
      $writer->write(0, 0, SparklineRenderer::render($this->samples, $this->maximum, $writer->width(), $this->label, $this->showValue, $this->unit), $this->style->highlight, $this->style->background);
    }
  }

  /** Keep this display-only widget out of input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Ignore input because samples are supplied by the application. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Reject line breaks and control characters in display attributes. */
  private function oneLine(string $text): string {
    if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Sparkline label and unit must be printable single-line text.');
    }
    return $text;
  }

}
