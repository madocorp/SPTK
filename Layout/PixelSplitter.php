<?php

namespace SPTK\Layout;

/** Splits an exact pixel rectangle with no implicit gaps or overlap. */
final class PixelSplitter {

  public static function split(Tile $area, string $direction, array $sizes, int $viewportWidth, int $viewportHeight): array {
    $horizontal = $direction === 'horizontal';
    $space = $horizontal ? $area->width : $area->height;
    $fixed = 0;
    $weights = 0;
    $parts = [];
    foreach ($sizes as $size) {
      if (str_ends_with($size, '*')) {
        if (!preg_match('/^\d+\*$/D', $size)) {
          throw new \InvalidArgumentException('Invalid pixel layout weight.');
        }
        $weight = (int)substr($size, 0, -1);
        $weight = max(1, $weight);
        $parts[] = ['weight' => $weight, 'size' => 0];
        $weights += $weight;
      } else {
        $value = PixelBox::dimension($size, $viewportWidth, $viewportHeight, $space);
        $parts[] = ['weight' => 0, 'size' => $value];
        $fixed += $value;
      }
    }
    $remaining = max(0, $space - $fixed);
    $used = 0;
    foreach ($parts as &$part) {
      if ($part['weight'] > 0 && $weights > 0) {
        $part['size'] = intdiv($remaining * $part['weight'], $weights);
        $used += $part['size'];
      }
    }
    unset($part);
    $extra = $remaining - $used;
    foreach ($parts as &$part) {
      if ($extra === 0) {
        break;
      }
      if ($part['weight'] > 0) {
        $part['size']++;
        $extra--;
      }
    }
    unset($part);
    $position = $horizontal ? $area->x : $area->y;
    $end = $position + $space;
    $tiles = [];
    foreach ($parts as $part) {
      $size = max(0, min($part['size'], $end - $position));
      $tiles[] = $horizontal
        ? new Tile($position, $area->y, $size, $area->height)
        : new Tile($area->x, $position, $area->width, $size);
      $position += $size;
    }
    return $tiles;
  }

}
