<?php

namespace SPTK\Rendering;

/** Formats recent numeric samples as a one-row Unicode block chart. */
final class Sparkline {

  /** Fill a fixed-width row with optional label and latest value. */
  public static function render(array $samples, float $maximum, int $width, string $label = '', bool $showValue = false, string $unit = ''): string {
    if ($width <= 0) {
      return '';
    }
    $labelText = $label === '' ? '' : $label . ' ';
    $valueWidth = TextMetrics::width(sprintf('%g', $maximum) . $unit);
    $value = $samples === [] ? '' : sprintf('%g', end($samples)) . $unit;
    $valueField = $showValue ? ' ' . $value . str_repeat(' ', max(0, $valueWidth - TextMetrics::width($value))) : '';
    if (TextMetrics::width($valueField) >= $width) {
      return implode('', array_slice(TextMetrics::glyphs(' ' . $value), -$width));
    }
    $labelText = TextMetrics::slice($labelText, 0, max(0, $width - TextMetrics::width($valueField)));
    $chartWidth = max(0, $width - TextMetrics::width($labelText) - TextMetrics::width($valueField));
    return $labelText . self::blocks($samples, $maximum, $chartWidth) . $valueField;
  }

  /** Map the newest samples to eight levels, with unfilled slots on the left. */
  private static function blocks(array $samples, float $maximum, int $width): string {
    if ($width <= 0) {
      return '';
    }
    $blocks = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
    $scale = $maximum > 0 ? $maximum : 1;
    $line = str_repeat('·', max(0, $width - count($samples)));
    foreach (array_slice($samples, -$width) as $value) {
      $level = max(0, min(7, (int)round(7 * $value / $scale)));
      $line .= $blocks[$level];
    }
    return $line;
  }

}
