<?php

namespace SPTK\Widgets\Graph;

use SPTK\Core\Color;

/** Validates graph series and options before any widget state changes. */
final class Data {

  public const DEFAULTS = [
    'title' => '', 'xLabel' => '', 'yLabel' => '', 'xUnit' => '', 'yUnit' => '',
    'xMin' => null, 'xMax' => null, 'yMin' => null, 'yMax' => null,
    'tickCount' => 5, 'legend' => null, 'grid' => false,
  ];
  private const COLORS = ['#00ffff', '#ffff00', '#ff55ff', '#55ff55', '#ff5555', '#ffffff'];

  /** Normalize all series, preserving point order and reserving bar slots. */
  public static function series(array $series): array {
    $result = [];
    foreach ($series as $item) {
      if (!is_array($item)) {
        throw new \InvalidArgumentException('Graph series must be arrays.');
      }
      $type = $item['type'] ?? 'line';
      if (!in_array($type, ['line', 'point', 'bar'], true)) {
        throw new \InvalidArgumentException('Graph series type must be line, point, or bar.');
      }
      $name = self::text($item['name'] ?? 'Series ' . (count($result) + 1));
      $color = Color::from($item['color'] ?? self::COLORS[count($result) % count(self::COLORS)]);
      if (!is_array($item['points'] ?? [])) {
        throw new \InvalidArgumentException('Graph points must be an array.');
      }
      $points = [];
      $xs = [];
      foreach ($item['points'] ?? [] as $point) {
        if (!is_array($point) || array_keys($point) !== [0, 1]) {
          throw new \InvalidArgumentException('Graph points must be [x, y] pairs.');
        }
        $x = self::number($point[0]);
        $y = self::number($point[1]);
        if ($type === 'bar' && in_array($x, $xs, true)) {
          throw new \InvalidArgumentException('Bar series cannot repeat an X coordinate.');
        }
        $xs[] = $x;
        $points[] = [$x, $y];
      }
      $result[] = ['name' => $name, 'type' => $type, 'color' => sprintf('#%02x%02x%02x', $color->r, $color->g, $color->b), 'points' => $points];
    }
    return $result;
  }

  /** Merge options and require complete increasing explicit axis bounds. */
  public static function options(array $current, array $updates): array {
    foreach ($updates as $key => $value) {
      if (!array_key_exists($key, self::DEFAULTS)) {
        throw new \InvalidArgumentException("Unknown Graph option: {$key}");
      }
      if (in_array($key, ['title', 'xLabel', 'yLabel', 'xUnit', 'yUnit'], true)) {
        $value = self::text($value);
      } else if (in_array($key, ['xMin', 'xMax', 'yMin', 'yMax'], true)) {
        $value = $value === null ? null : self::number($value);
      } else if ($key === 'tickCount') {
        if (!is_int($value) || $value < 2 || $value > 12) {
          throw new \InvalidArgumentException('Graph tickCount must be an integer from 2 through 12.');
        }
      } else if (!is_bool($value) && !($key === 'legend' && $value === null)) {
        throw new \InvalidArgumentException("Graph {$key} must be boolean.");
      }
      $current[$key] = $value;
    }
    foreach (['x', 'y'] as $axis) {
      $min = $current[$axis . 'Min'];
      $max = $current[$axis . 'Max'];
      if (($min === null) !== ($max === null) || ($min !== null && $min >= $max)) {
        throw new \InvalidArgumentException('Graph bounds require an increasing min/max pair, or two nulls.');
      }
    }
    return $current;
  }

  /** Convert a numeric coordinate or bound to a finite float. */
  public static function number(mixed $value): float {
    if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || !is_finite((float)$value)) {
      throw new \InvalidArgumentException('Graph coordinates and bounds must be finite numbers.');
    }
    return (float)$value;
  }

  /** Normalize line breaks and reject invalid or unprintable text. */
  private static function text(mixed $value): string {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
      throw new \InvalidArgumentException('Graph labels must be UTF-8 strings.');
    }
    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
      throw new \InvalidArgumentException('Graph labels must be printable.');
    }
    return $value;
  }

}
