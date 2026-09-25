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

  public static function horizontal(Tile $grid, array $sizes): array {
    $parsed = self::parseSizes($sizes);
    $columns = $grid->height;
    $remainingSpace = $columns - $parsed['fixedSize'];
    $result = [];
    $x = 0;
    foreach ($parsed['sizes'] as $size) {
      if ($size['fixed']) {
        $width = $size['value'];
        $result[] = new Tile($grid->x + $x, $grid->y, $width, $grid->height);
      } else {
        $width = (int)($remainingSpace * $size['value'] / $parsed['sumWeight']);
        $result[] = new Tile($grid->x + $x, $grid->y, $width, $grid->height);
      }
      $x += $width + 1;
    }
    return $result;

  }

  public static function vertical(Tile $grid, array $sizes): array {
    $parsed = self::parseSizes($sizes);
    $rows = $grid->height;
    $remainingSpace = $rows - $parsed['fixedSize'];
    $result = [];
    $y = 0;
    foreach ($parsed['sizes'] as $size) {
      if ($size['fixed']) {
        $height = $size['value'];
        $result[] = new Tile($grid->x, $grid->y + $y, $grid->width, $height);
      } else {
        $height = (int)($remainingSpace * $size['value'] / $parsed['sumWeight']);
        $result[] = new Tile($grid->x, $grid->y + $y, $grid->width, $height);
      }
      $y += $height + 1;
    }
    return $result;
  }

}
/*
require_once 'Tile.php';
$t = new Tile(0, 0, 80, 25);
$res = Splitter::vertical($t, ["1", "2", "1*"]);
var_dump($res);
*/