<?php

namespace SPTK\Layout;

/** Splits a grid horizontally or vertically to smaller tiles */
final class Splitter {

  private static function parseSizes(array $sizes): array {
    $fixedSize = count($sizes) - 1;
    $sumWeight = 0;
    $parsedSizes = [];
    foreach ($sizes as $size) {
      if (strpos($size, '*') === false) {
        $value = (int)$size;
        $parsedSizes[] = ['fixed' => true, 'value' => $value];
        $fixedSize += (int)$value;
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

  private static function allocateSizes(array $sizes, int $space): array {
    $parsed = self::parseSizes($sizes);
    $remaining = $space - $parsed['fixedSize'];
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
    $allocated = self::allocateSizes($sizes, $columns - (count($sizes) - 1));
    $result = [];
    $x = 0;
    foreach ($allocated as $width) {
      $result[] = new Tile($grid->x + $x, $grid->y, $width, $grid->height);
      $x += $width + 2;
    }
    return $result;

  }

  public static function vertical(Tile $grid, array $sizes): array {
    $rows = $grid->height;
    $allocated = self::allocateSizes($sizes, $rows);
    $result = [];
    $y = 0;
    foreach ($allocated as $height) {
      $result[] = new Tile($grid->x, $grid->y + $y, $grid->width, $height);
      $y += $height + 1;
    }
    return $result;
  }

}
