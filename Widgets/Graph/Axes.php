<?php

namespace SPTK\Widgets\Graph;

/** Computes linear ranges, grouped bar spacing, ticks, and stable coordinate ratios. */
final class Axes {

  /** Cover all points, padding constant axes and including bar widths and zero. */
  public static function ranges(array $series, array $options): array {
    $xs = [];
    $ys = [];
    $barXs = [];
    foreach ($series as $item) {
      foreach ($item['points'] as [$x, $y]) {
        $xs[] = $x;
        $ys[] = $y;
        if ($item['type'] === 'bar') {
          $barXs[] = $x;
        }
      }
    }
    sort($barXs, SORT_NUMERIC);
    $spacing = null;
    for ($i = 1; $i < count($barXs); $i++) {
      $distance = $barXs[$i] - $barXs[$i - 1];
      if ($distance > 0 && is_finite($distance)) {
        $spacing = $spacing === null ? $distance : min($spacing, $distance);
      }
    }
    $spacing ??= 1.0;
    if ($barXs !== []) {
      $ys[] = 0.0;
      $xs[] = max(-PHP_FLOAT_MAX, $barXs[0] - $spacing * 0.4);
      $xs[] = min(PHP_FLOAT_MAX, $barXs[count($barXs) - 1] + $spacing * 0.4);
    }
    [$xMin, $xMax] = self::extent($xs);
    [$yMin, $yMax] = self::extent($ys);
    return [
      'xMin' => $options['xMin'] ?? $xMin, 'xMax' => $options['xMax'] ?? $xMax,
      'yMin' => $options['yMin'] ?? $yMin, 'yMax' => $options['yMax'] ?? $yMax,
      'barSpacing' => $spacing,
    ];
  }

  /** Return readable round ticks with endpoint fallback for extreme ranges. */
  public static function ticks(float $min, float $max, int $count, string $unit): array {
    $span = $max - $min;
    $step = $span / ($count - 1);
    $values = [];
    if (is_finite($step) && $step > 0) {
      $power = pow(10, floor(log10($step)));
      if ($power > 0) {
        $fraction = $step / $power;
        $nice = $fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10));
        $step = $nice * $power;
        $first = ceil($min / $step) * $step;
        for ($i = 0; $i < 12; $i++) {
          $value = $first + $i * $step;
          if (!is_finite($value) || $value > $max) {
            break;
          }
          if ($value >= $min) {
            $values[] = $value;
          }
        }
      }
    }
    if (count($values) < 2) {
      $values = [$min, $max];
    }
    $ticks = [];
    $precision = is_finite($step) && $step > 0 ? max(0, min(14, (int)-floor(log10($step)) + 1)) : 0;
    foreach ($values as $value) {
      if (($value != 0 && abs($value) < 0.0001) || abs($value) >= 1e7) {
        $label = sprintf('%.6g', $value);
      } else {
        $label = number_format($value, $precision, '.', '');
        if ($precision > 0) {
          $label = rtrim(rtrim($label, '0'), '.');
        }
      }
      $ticks[] = [$value, ($label === '-0' ? '0' : $label) . $unit];
    }
    if (count(array_unique(array_column($ticks, 1))) !== count($ticks)) {
      foreach ($ticks as $i => [$value]) {
        $ticks[$i][1] = sprintf('%.17g', $value) . $unit;
      }
    }
    return $ticks;
  }

  /** Map a coordinate without overflowing a range spanning both float extremes. */
  public static function ratio(float $value, float $min, float $max): float {
    $span = $max - $min;
    if (is_finite($span) && $span > 0) {
      $ratio = ($value - $min) / $span;
    } else {
      $scale = max(abs($min), abs($max), PHP_FLOAT_MIN);
      $ratio = ($value / $scale - $min / $scale) / ($max / $scale - $min / $scale);
    }
    return max(-1e9, min(1e9, $ratio));
  }

  /** Give empty and constant data a usable finite axis interval. */
  private static function extent(array $values): array {
    if ($values === []) {
      return [0.0, 1.0];
    }
    $min = min($values);
    $max = max($values);
    if ($min === $max) {
      $padding = max(0.5, abs($min) * 0.05);
      $min = max(-PHP_FLOAT_MAX, $min - $padding);
      $max = min(PHP_FLOAT_MAX, $max + $padding);
    }
    return [$min, $max];
  }

}
