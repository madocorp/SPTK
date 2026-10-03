<?php

namespace SPTK\Rendering\Glyph;

/** Converts Unicode box drawing junctions into weighted pixel strokes. */
final class BoxGlyph {

  private const ARMS = [
    '─' => [1, 1, 0, 0, 0], '━' => [2, 2, 0, 0, 0], '│' => [0, 0, 1, 1, 0], '┃' => [0, 0, 2, 2, 0],
    '┄' => [1, 1, 0, 0, 3], '┅' => [2, 2, 0, 0, 3], '┆' => [0, 0, 1, 1, 3], '┇' => [0, 0, 2, 2, 3],
    '┈' => [1, 1, 0, 0, 4], '┉' => [2, 2, 0, 0, 4], '┊' => [0, 0, 1, 1, 4], '┋' => [0, 0, 2, 2, 4],
    '┌' => [0, 1, 0, 1, 0], '┍' => [0, 2, 0, 1, 0], '┎' => [0, 1, 0, 2, 0], '┏' => [0, 2, 0, 2, 0],
    '┐' => [1, 0, 0, 1, 0], '┑' => [2, 0, 0, 1, 0], '┒' => [1, 0, 0, 2, 0], '┓' => [2, 0, 0, 2, 0],
    '└' => [0, 1, 1, 0, 0], '┕' => [0, 2, 1, 0, 0], '┖' => [0, 1, 2, 0, 0], '┗' => [0, 2, 2, 0, 0],
    '┘' => [1, 0, 1, 0, 0], '┙' => [2, 0, 1, 0, 0], '┚' => [1, 0, 2, 0, 0], '┛' => [2, 0, 2, 0, 0],
    '├' => [0, 1, 1, 1, 0], '┝' => [0, 2, 1, 1, 0], '┞' => [0, 1, 2, 1, 0], '┟' => [0, 1, 1, 2, 0],
    '┠' => [0, 1, 2, 2, 0], '┡' => [0, 2, 2, 1, 0], '┢' => [0, 2, 1, 2, 0], '┣' => [0, 2, 2, 2, 0],
    '┤' => [1, 0, 1, 1, 0], '┥' => [2, 0, 1, 1, 0], '┦' => [1, 0, 2, 1, 0], '┧' => [1, 0, 1, 2, 0],
    '┨' => [1, 0, 2, 2, 0], '┩' => [2, 0, 2, 1, 0], '┪' => [2, 0, 1, 2, 0], '┫' => [2, 0, 2, 2, 0],
    '┬' => [1, 1, 0, 1, 0], '┭' => [2, 1, 0, 1, 0], '┮' => [1, 2, 0, 1, 0], '┯' => [2, 2, 0, 1, 0],
    '┰' => [1, 1, 0, 2, 0], '┱' => [2, 1, 0, 2, 0], '┲' => [1, 2, 0, 2, 0], '┳' => [2, 2, 0, 2, 0],
    '┴' => [1, 1, 1, 0, 0], '┵' => [2, 1, 1, 0, 0], '┶' => [1, 2, 1, 0, 0], '┷' => [2, 2, 1, 0, 0],
    '┸' => [1, 1, 2, 0, 0], '┹' => [2, 1, 2, 0, 0], '┺' => [1, 2, 2, 0, 0], '┻' => [2, 2, 2, 0, 0],
    '┼' => [1, 1, 1, 1, 0], '┽' => [2, 1, 1, 1, 0], '┾' => [1, 2, 1, 1, 0], '┿' => [2, 2, 1, 1, 0],
    '╀' => [1, 1, 2, 1, 0], '╁' => [1, 1, 1, 2, 0], '╂' => [1, 1, 2, 2, 0], '╃' => [2, 1, 2, 1, 0],
    '╄' => [1, 2, 2, 1, 0], '╅' => [2, 1, 1, 2, 0], '╆' => [1, 2, 1, 2, 0], '╇' => [2, 2, 2, 1, 0],
    '╈' => [2, 2, 1, 2, 0], '╉' => [2, 1, 2, 2, 0], '╊' => [1, 2, 2, 2, 0], '╋' => [2, 2, 2, 2, 0],
    '╌' => [1, 1, 0, 0, 2], '╍' => [2, 2, 0, 0, 2], '╎' => [0, 0, 1, 1, 2], '╏' => [0, 0, 2, 2, 2],
    '═' => [3, 3, 0, 0, 0], '║' => [0, 0, 3, 3, 0], '╒' => [0, 3, 0, 1, 0], '╓' => [0, 1, 0, 3, 0],
    '╔' => [0, 3, 0, 3, 0], '╕' => [3, 0, 0, 1, 0], '╖' => [1, 0, 0, 3, 0], '╗' => [3, 0, 0, 3, 0],
    '╘' => [0, 3, 1, 0, 0], '╙' => [0, 1, 3, 0, 0], '╚' => [0, 3, 3, 0, 0], '╛' => [3, 0, 1, 0, 0],
    '╜' => [1, 0, 3, 0, 0], '╝' => [3, 0, 3, 0, 0], '╞' => [0, 3, 1, 1, 0], '╟' => [0, 1, 3, 3, 0],
    '╠' => [0, 3, 3, 3, 0], '╡' => [3, 0, 1, 1, 0], '╢' => [1, 0, 3, 3, 0], '╣' => [3, 0, 3, 3, 0],
    '╤' => [3, 3, 0, 1, 0], '╥' => [1, 1, 0, 3, 0], '╦' => [3, 3, 0, 3, 0], '╧' => [3, 3, 1, 0, 0],
    '╨' => [1, 1, 3, 0, 0], '╩' => [3, 3, 3, 0, 0], '╪' => [3, 3, 1, 1, 0], '╫' => [1, 1, 3, 3, 0],
    '╬' => [3, 3, 3, 3, 0], '╴' => [1, 0, 0, 0, 0], '╵' => [0, 0, 1, 0, 0], '╶' => [0, 1, 0, 0, 0],
    '╷' => [0, 0, 0, 1, 0], '╸' => [2, 0, 0, 0, 0], '╹' => [0, 0, 2, 0, 0], '╺' => [0, 2, 0, 0, 0],
    '╻' => [0, 0, 0, 2, 0], '╼' => [1, 2, 0, 0, 0], '╽' => [0, 0, 1, 2, 0], '╾' => [2, 1, 0, 0, 0],
    '╿' => [0, 0, 2, 1, 0],
  ];

  /** Paint the arms, curves, and dash patterns for a box drawing glyph. */
  public static function draw(GlyphMask $mask, string $glyph, int $code): void {
    if ($code >= 0x2571 && $code <= 0x2573) {
      self::diagonal($mask, $code);
      return;
    }
    if ($code >= 0x256d && $code <= 0x2570) {
      self::roundedCorner($mask, $code);
      return;
    }
    [$left, $right, $up, $down, $dash] = self::ARMS[$glyph];
    $width = $mask->width();
    $height = $mask->height();
    $centerX = intdiv($width, 2);
    $centerY = intdiv($height, 2);
    $light = max(1, intdiv(min($width, $height), 10));
    $arms = [$left, $right, $up, $down];
    $gap = max(1, min($light + 1, intdiv(min($width, $height) - 1, 2)));
    foreach ($arms as $direction => $style) {
      if ($style === 0) {
        continue;
      }
      $horizontal = $direction < 2;
      $sign = $direction % 2 === 0 ? -1 : 1;
      $axis = $horizontal ? $centerX : $centerY;
      $cross = $horizontal ? $centerY : $centerX;
      $end = $sign < 0 ? 0 : ($horizontal ? $width : $height) - 1;
      $weight = $style === 2 ? 2 * $light : $light;
      $lanes = $style === 3 ? [-1, 1] : [0];
      foreach ($lanes as $lane) {
        $start = self::strokeStart($lane, $axis, $sign, $horizontal, $direction, $arms, $gap);
        if ($horizontal) {
          $mask->line($start, $cross + $lane * $gap, $end, $cross + $lane * $gap, $weight);
        } else {
          $mask->line($cross + $lane * $gap, $start, $cross + $lane * $gap, $end, $weight);
        }
      }
    }
    if ($dash > 0) {
      self::applyDashes($mask, $left > 0, $dash);
    }
  }

  /** Return the start coordinate for a stroke through a mixed-weight junction. */
  private static function strokeStart(int $lane, int $axis, int $sign, bool $horizontal, int $direction, array $arms, int $gap): int {
    $perpendicular = $horizontal ? [$arms[2], $arms[3]] : [$arms[0], $arms[1]];
    if ($lane !== 0) {
      $near = $lane < 0 ? 0 : 1;
      $far = 1 - $near;
      if ($perpendicular[$near] === 3) {
        return $axis + $sign * $gap;
      }
      if ($perpendicular[$far] === 3) {
        return $axis - $sign * $gap;
      }
    } else if (in_array(3, $perpendicular, true) && $arms[$direction ^ 1] === 0) {
      return $axis + (($perpendicular[0] === 3 && $perpendicular[1] === 3) ? $sign : -$sign) * $gap;
    }
    return $axis;
  }

  /** Rasterize slash and backslash combinations. */
  private static function diagonal(GlyphMask $mask, int $code): void {
    $width = $mask->width();
    $height = $mask->height();
    $weight = max(1, intdiv(min($width, $height), 10));
    if ($code !== 0x2572) {
      $mask->line($width - 1, 0, 0, $height - 1, $weight);
    }
    if ($code !== 0x2571) {
      $mask->line(0, 0, $width - 1, $height - 1, $weight);
    }
  }

  /** Draw one rounded box corner with straight joins and a sampled quarter arc. */
  private static function roundedCorner(GlyphMask $mask, int $code): void {
    $width = $mask->width();
    $height = $mask->height();
    $centerX = intdiv($width, 2);
    $centerY = intdiv($height, 2);
    [$sx, $sy] = [[1, 1], [-1, 1], [-1, -1], [1, -1]][$code - 0x256d];
    $radius = max(1, min($centerX, $centerY, $width - 1 - $centerX, $height - 1 - $centerY));
    $weight = max(1, intdiv(min($width, $height), 10));
    $mask->line($centerX + $sx * $radius, $centerY, $sx > 0 ? $width - 1 : 0, $centerY, $weight);
    $mask->line($centerX, $centerY + $sy * $radius, $centerX, $sy > 0 ? $height - 1 : 0, $weight);
    $steps = max(4, $radius * 3);
    for ($step = 0; $step <= $steps; $step++) {
      $angle = $step * M_PI / (2 * $steps);
      $x = (int)round($centerX + $sx * $radius * (1 - cos($angle)));
      $y = (int)round($centerY + $sy * $radius * (1 - sin($angle)));
      $mask->rect($x, $y, $weight, $weight);
    }
  }

  /** Remove regular gaps from a dashed or dotted stroke. */
  private static function applyDashes(GlyphMask $mask, bool $horizontal, int $dash): void {
    $length = $horizontal ? $mask->width() : $mask->height();
    for ($y = 0; $y < $mask->height(); $y++) {
      for ($x = 0; $x < $mask->width(); $x++) {
        $position = $horizontal ? $x : $y;
        if (($position * $dash) % $length >= $length * 0.65) {
          $mask->point($x, $y, false);
        }
      }
    }
  }

}
