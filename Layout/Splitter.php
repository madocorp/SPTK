<?php

namespace SPTK\Layout;

/** Splits a grid horizontally or vertically to smaller tiles */
final class Splitter {

  /** A zero-sized tile consumes neither its own leading gap nor a leading gap before visible content. */
  private static function collapsedGapBefore(array $sizes, int $index): bool {
    if ($index === 0) {
      return false;
    }
    if (in_array($sizes[$index], ['0', '0*'], true)) {
      return true;
    }
    for ($previous = 0; $previous < $index; $previous++) {
      if ($sizes[$previous] !== '0') {
        return false;
      }
    }
    return true;
  }

  /** Resolve fixed cell sizes, including percentages of the parent tile. */
  private static function parseSizes(array $sizes, int $space): array {
    $fixedSize = count($sizes) - 1;
    $sumWeight = 0;
    $parsedSizes = [];
    foreach ($sizes as $size) {
      if (strpos($size, '*') === false) {
        $value = str_ends_with($size, '%') ? (int)round($space * (float)$size / 100) : (int)$size;
        $parsedSizes[] = ['fixed' => true, 'value' => $value];
        $fixedSize += $value;
      } else {
        $value = max(1, (int)str_replace('*', '', $size));
        $parsedSizes[] = ['fixed' => false, 'value' => $value];
        $sumWeight += $value;
      }
    }
    return [
      'fixedSize' => $fixedSize,
      'sumWeight' => $sumWeight,
      'sizes' => $parsedSizes
    ];
  }

  /** Distribute cells left after fixed sizes and gaps to weighted children. */
  private static function allocateSizes(array $sizes, int $space, int $referenceSpace): array {
    $parsed = self::parseSizes($sizes, $referenceSpace);
    $remaining = max(0, $space - $parsed['fixedSize']);
    $allocated = [];
    $weighted = [];
    $used = 0;
    foreach ($parsed['sizes'] as $index => $size) {
      if ($size['fixed']) {
        $allocated[$index] = $size['value'];
      } else {
        $allocated[$index] = (int)($remaining * $size['value'] / $parsed['sumWeight']);
        $weighted[] = $index;
        $used += $allocated[$index];
      }
    }
    $extra = $remaining - $used;
    foreach ($weighted as $index) {
      if ($extra === 0) {
        break;
      }
      $allocated[$index]++;
      $extra--;
    }
    return $allocated;
  }

  public static function horizontal(Tile $grid, array $sizes): array {
    $columns = $grid->width;
    $collapsedGaps = 0;
    foreach ($sizes as $index => $size) {
      if (self::collapsedGapBefore($sizes, $index)) {
        $collapsedGaps++;
      }
    }
    $allocated = self::allocateSizes($sizes, $columns - (count($sizes) - 1) + 2 * $collapsedGaps, $columns);
    $result = [];
    $x = 0;
    foreach ($allocated as $index => $width) {
      $result[] = new Tile($grid->x + $x, $grid->y, $width, $grid->height);
      $x += $width + (isset($sizes[$index + 1]) && self::collapsedGapBefore($sizes, $index + 1) ? 0 : 2);
    }
    return $result;

  }

  public static function vertical(Tile $grid, array $sizes): array {
    $rows = $grid->height;
    $collapsedGaps = 0;
    foreach ($sizes as $index => $size) {
      if (self::collapsedGapBefore($sizes, $index)) {
        $collapsedGaps++;
      }
    }
    $allocated = self::allocateSizes($sizes, $rows + $collapsedGaps, $rows);
    $result = [];
    $y = 0;
    foreach ($allocated as $index => $height) {
      $result[] = new Tile($grid->x, $grid->y + $y, $grid->width, $height);
      $y += $height + (isset($sizes[$index + 1]) && self::collapsedGapBefore($sizes, $index + 1) ? 0 : 1);
    }
    return $result;
  }

}
