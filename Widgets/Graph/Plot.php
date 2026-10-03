<?php

namespace SPTK\Widgets\Graph;

use SPTK\Core\Color;

/** Paints clipped lines, point markers, and grouped bars into a GD plot rectangle. */
final class Plot {

  /** Map an X value into pixel coordinates. */
  private static function mapX(float $value, array $ranges, array $rect): float {
    return $rect[0] + Axes::ratio($value, $ranges['xMin'], $ranges['xMax']) * ($rect[2] - $rect[0]);
  }

  /** Map a Y value into upward-increasing plot coordinates. */
  private static function mapY(float $value, array $ranges, array $rect): float {
    return $rect[3] - Axes::ratio($value, $ranges['yMin'], $ranges['yMax']) * ($rect[3] - $rect[1]);
  }

  /** Clip crossing segments even when both endpoints are outside the plot. */
  private static function clipLine(array $first, array $last, array $rect): ?array {
    $dx = $last[0] - $first[0];
    $dy = $last[1] - $first[1];
    $start = 0.0;
    $end = 1.0;
    $edges = [[-$dx, $first[0] - $rect[0]], [$dx, $rect[2] - $first[0]], [-$dy, $first[1] - $rect[1]], [$dy, $rect[3] - $first[1]]];
    foreach ($edges as [$direction, $distance]) {
      if ($direction == 0) {
        if ($distance < 0) {
          return null;
        }
        continue;
      }
      $ratio = $distance / $direction;
      if ($direction < 0) {
        $start = max($start, $ratio);
      } else {
        $end = min($end, $ratio);
      }
      if ($start > $end) {
        return null;
      }
    }
    return [$first[0] + $start * $dx, $first[1] + $start * $dy, $first[0] + $end * $dx, $first[1] + $end * $dy];
  }

  /** Draw each series in its supplied order, clipping all marks to the axes. */
  public function paint(\GdImage $canvas, array $series, array $ranges, array $rect, int $rowHeight): void {
    [$left, $top, $right, $bottom] = $rect;
    imagesetclip($canvas, $left, $top, $right, $bottom);
    $barCount = 0;
    foreach ($series as $item) {
      $barCount += $item['type'] === 'bar' ? 1 : 0;
    }
    $barIndex = 0;
    foreach ($series as $item) {
      $rgb = Color::from($item['color']);
      $color = imagecolorallocate($canvas, $rgb->r, $rgb->g, $rgb->b);
      if ($item['type'] === 'bar') {
        $this->bars($canvas, $item['points'], $ranges, $rect, $color, $barIndex, $barCount);
        $barIndex++;
      } else {
        $this->points($canvas, $item, $ranges, $rect, $color, $rowHeight);
      }
    }
    imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
  }

  /** Connect line points and draw markers only for points inside the plot. */
  private function points(\GdImage $canvas, array $item, array $ranges, array $rect, int $color, int $rowHeight): void {
    $previous = null;
    $diameter = max(3, min(7, (int)round($rowHeight * 0.3)));
    imagesetthickness($canvas, 2);
    foreach ($item['points'] as [$x, $y]) {
      $point = [self::mapX($x, $ranges, $rect), self::mapY($y, $ranges, $rect)];
      if ($item['type'] === 'line' && $previous !== null) {
        $segment = self::clipLine($previous, $point, $rect);
        if ($segment !== null) {
          imageline($canvas, (int)round($segment[0]), (int)round($segment[1]), (int)round($segment[2]), (int)round($segment[3]), $color);
        }
      }
      if ($point[0] >= $rect[0] && $point[0] <= $rect[2] && $point[1] >= $rect[1] && $point[1] <= $rect[3]) {
        imagefilledellipse($canvas, (int)round($point[0]), (int)round($point[1]), $diameter, $diameter, $color);
      }
      $previous = $point;
    }
    imagesetthickness($canvas, 1);
  }

  /** Reserve a slot for each bar series and draw positive or negative bars from zero. */
  private function bars(\GdImage $canvas, array $points, array $ranges, array $rect, int $color, int $index, int $count): void {
    $group = $ranges['barSpacing'] * 0.8;
    $slot = $group / $count;
    $baseline = self::mapY(0, $ranges, $rect);
    foreach ($points as [$x, $y]) {
      $start = $x - $group / 2 + $index * $slot;
      $x1 = self::mapX($start, $ranges, $rect);
      $x2 = self::mapX($start + $slot * 0.9, $ranges, $rect);
      $end = self::mapY($y, $ranges, $rect);
      $top = max($rect[1], min($end, $baseline));
      $bottom = min($rect[3], max($end, $baseline));
      $left = max($rect[0], $x1);
      $right = min($rect[2], $x2);
      if ($y !== 0.0 && $left <= $right && $top <= $bottom) {
        imagefilledrectangle($canvas, (int)round($left), (int)round($top), (int)round($right), (int)round($bottom), $color);
      }
    }
  }


}
