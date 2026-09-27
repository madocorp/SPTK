<?php

namespace SPTK\Widgets\Table;

/** Finds table viewport edges and advances by one visible page. */
final class Navigator {

  /** Move to the top or bottom visible row, paging if already at that edge. */
  public static function pageRow(int $cursor, int $scroll, int $height, int $count, bool $end): array {
    if ($count < 1) {
      return [0, 0];
    }
    $height = max(1, $height);
    $edge = $end ? min($count - 1, $scroll + $height - 1) : $scroll;
    if ($cursor === $edge) {
      $scroll = max(0, min(max(0, $count - $height), $scroll + ($end ? $height : -$height)));
      $edge = $end ? min($count - 1, $scroll + $height - 1) : $scroll;
    }
    return [$edge, $scroll];
  }

  /** Move to the first or last visible field, paging if already at that edge. */
  public static function pageColumn(int $cursor, int $scroll, int $width, array $widths, bool $end): array {
    if ($widths === []) {
      return [0, 0];
    }
    $width = max(1, $width);
    $edge = self::visibleColumn($widths, $scroll, $width, $end);
    if ($cursor === $edge) {
      $scroll = max(0, min(max(0, array_sum($widths) - $width), $scroll + ($end ? $width : -$width)));
      $edge = self::visibleColumn($widths, $scroll, $width, $end);
    }
    return [$edge, $scroll];
  }

  /** Find the field intersecting a viewport edge. */
  private static function visibleColumn(array $widths, int $scroll, int $width, bool $end): int {
    $position = 0;
    $edge = 0;
    foreach ($widths as $column => $fieldWidth) {
      if ($position + $fieldWidth > $scroll && $position < $scroll + $width) {
        $edge = $column;
        if (!$end) {
          break;
        }
      }
      $position += $fieldWidth;
    }
    return $edge;
  }

}
