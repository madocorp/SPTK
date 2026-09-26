<?php

namespace SPTK\Rendering\Glyph;

/** Rasterizes geometric Unicode symbols into font-sized masks. */
final class ShapeGlyph {

  /** Draw one square, circle, polygon, or directional shape glyph. */
  public static function draw(GlyphMask $mask, int $code): void {
    $width = $mask->width();
    $height = $mask->height();
    $size = max(1, min($width, $height) - 2);
    if ($size % 2 === 0) {
      $size--;
    }
    if (self::isSmall($code)) {
      $size = max(1, 2 * (int)floor($size / 4) + 1);
    } else if ($code === 0x25fb || $code === 0x25fc) {
      $size = max(1, $size - 2);
    } else if ($code === 0x25ef) {
      $size = min($width, $height);
      if ($size % 2 === 0) {
        $size--;
      }
    }
    $left = intdiv($width - $size, 2);
    $top = intdiv($height - $size, 2);
    $step = 2 / max(1, $size - 1);
    [$kind, $polygon] = self::outline($code);
    for ($py = 0; $py < $size; $py++) {
      for ($px = 0; $px < $size; $px++) {
        $x = $size === 1 ? 0 : $px * $step - 1;
        $y = $size === 1 ? 0 : $py * $step - 1;
        $inside = self::inside($kind, $polygon, $x, $y);
        $edge = $inside && (!self::inside($kind, $polygon, $x - $step, $y)
          || !self::inside($kind, $polygon, $x + $step, $y)
          || !self::inside($kind, $polygon, $x, $y - $step)
          || !self::inside($kind, $polygon, $x, $y + $step));
        $on = $inside && ($edge || self::filled($code, $x, $y, $step, $px, $py));
        $on = self::special($code, $x, $y, $inside, $edge, $on);
        if ($on) {
          $mask->point($left + $px, $top + $py);
        }
      }
    }
  }

  /** Return whether a symbol uses the smaller presentation size. */
  private static function isSmall(int $code): bool {
    return in_array($code, [
      0x25aa, 0x25ab, 0x25b4, 0x25b5, 0x25b8, 0x25b9, 0x25be, 0x25bf,
      0x25c2, 0x25c3, 0x25e6, 0x25fd, 0x25fe,
    ], true);
  }

  /** Choose the normalized outline used to rasterize this codepoint. */
  private static function outline(int $code): array {
    if (($code >= 0x25c9 && $code <= 0x25e1 && $code !== 0x25ca) || $code === 0x25e6 || $code === 0x25ef || ($code >= 0x25f4 && $code <= 0x25f7)) {
      return ['circle', []];
    }
    if ($code === 0x25a2) {
      return ['rounded', []];
    }
    if ($code >= 0x25c6 && $code <= 0x25c8) {
      return ['diamond', []];
    }
    if ($code === 0x25ca) {
      return ['polygon', [[0, -1], [0.6, 0], [0, 1], [-0.6, 0]]];
    }
    if ($code === 0x25ac || $code === 0x25ad) {
      return ['polygon', [[-1, -0.5], [1, -0.5], [1, 0.5], [-1, 0.5]]];
    }
    if ($code === 0x25ae || $code === 0x25af) {
      return ['polygon', [[-0.5, -1], [0.5, -1], [0.5, 1], [-0.5, 1]]];
    }
    if ($code === 0x25b0 || $code === 0x25b1) {
      return ['polygon', [[-0.5, -0.6], [1, -0.6], [0.5, 0.6], [-1, 0.6]]];
    }
    return self::arrowOutline($code) ?? self::cornerOutline($code) ?? ['square', []];
  }

  /** Build a rotated triangle outline for directional triangles. */
  private static function arrowOutline(int $code): ?array {
    $direction = match (true) {
      ($code >= 0x25b2 && $code <= 0x25b5) || ($code >= 0x25ec && $code <= 0x25ee) => 0,
      $code >= 0x25b6 && $code <= 0x25bb => 1,
      $code >= 0x25bc && $code <= 0x25bf => 2,
      $code >= 0x25c0 && $code <= 0x25c5 => 3,
      default => -1,
    };
    if ($direction < 0) {
      return null;
    }
    $polygon = [[0, -1], [1, 1], [-1, 1]];
    if (in_array($code, [0x25ba, 0x25bb, 0x25c4, 0x25c5], true)) {
      $polygon = [[0, -1], [0.5, 1], [-0.5, 1]];
    }
    for ($turn = 0; $turn < $direction; $turn++) {
      $polygon = array_map(static fn(array $point): array => [-$point[1], $point[0]], $polygon);
    }
    return ['polygon', $polygon];
  }

  /** Build a right-angle polygon for triangular corner symbols. */
  private static function cornerOutline(int $code): ?array {
    $corner = match ($code) {
      0x25e2, 0x25ff => 2,
      0x25e3, 0x25fa => 3,
      0x25e4, 0x25f8 => 0,
      0x25e5, 0x25f9 => 1,
      default => -1,
    };
    if ($corner < 0) {
      return null;
    }
    $polygon = [[-1, -1], [1, -1], [-1, 1]];
    for ($turn = 0; $turn < $corner; $turn++) {
      $polygon = array_map(static fn(array $point): array => [-$point[1], $point[0]], $polygon);
    }
    return ['polygon', $polygon];
  }

  /** Test whether a normalized point lies inside a shape outline. */
  private static function inside(string $kind, array $polygon, float $x, float $y): bool {
    if (abs($x) > 1.000001 || abs($y) > 1.000001) {
      return false;
    }
    if ($kind === 'circle') {
      return $x * $x + $y * $y <= 1.000001;
    }
    if ($kind === 'diamond') {
      return abs($x) + abs($y) <= 1.000001;
    }
    if ($kind === 'rounded') {
      return hypot(max(0, abs($x) - 0.55), max(0, abs($y) - 0.55)) <= 0.450001;
    }
    if ($kind !== 'polygon') {
      return true;
    }
    return self::insidePolygon($polygon, $x, $y);
  }

  /** Check polygon edge signs to determine whether a point is inside. */
  private static function insidePolygon(array $polygon, float $x, float $y): bool {
    $orientation = 0;
    foreach ($polygon as $index => [$ax, $ay]) {
      [$bx, $by] = $polygon[($index + 1) % count($polygon)];
      $cross = ($bx - $ax) * ($y - $ay) - ($by - $ay) * ($x - $ax);
      if (abs($cross) < 0.000001) {
        continue;
      }
      $side = $cross > 0 ? 1 : -1;
      if ($orientation !== 0 && $orientation !== $side) {
        return false;
      }
      $orientation = $side;
    }
    return true;
  }

  /** Decide whether an interior pixel belongs to this symbol's fill pattern. */
  private static function filled(int $code, float $x, float $y, float $step, int $px, int $py): bool {
    if (in_array($code, [
      0x25a0, 0x25aa, 0x25ac, 0x25ae, 0x25b0, 0x25b2, 0x25b4, 0x25b6,
      0x25b8, 0x25ba, 0x25bc, 0x25be, 0x25c0, 0x25c2, 0x25c4, 0x25c6,
      0x25cf, 0x25e2, 0x25e3, 0x25e4, 0x25e5, 0x25fc, 0x25fe,
    ], true)) {
      return true;
    }
    if ($code >= 0x25f0 && $code <= 0x25f7) {
      [$sx, $sy] = [[-1, -1], [-1, 1], [1, 1], [1, -1]][($code - 0x25f0) % 4];
      return (abs($x) < $step / 2 && $y * $sy >= 0) || (abs($y) < $step / 2 && $x * $sx >= 0);
    }
    return match ($code) {
      0x25a3 => max(abs($x), abs($y)) <= 0.4,
      0x25a4 => $py % 3 === 0,
      0x25a5, 0x25cd => $px % 3 === 0,
      0x25a6 => $px % 3 === 0 || $py % 3 === 0,
      0x25a7 => ($px - $py) % 3 === 0,
      0x25a8 => ($px + $py) % 3 === 0,
      0x25a9 => ($px - $py) % 3 === 0 || ($px + $py) % 3 === 0,
      0x25c8 => abs($x) + abs($y) <= 0.4,
      0x25c9, 0x25ec => hypot($x, $y) <= 0.3,
      0x25ce => abs(hypot($x, $y) - 0.45) < $step / 2,
      0x25d0, 0x25e7, 0x25ed => $x <= 0,
      0x25d1, 0x25e8, 0x25ee => $x >= 0,
      0x25d2 => $y >= 0,
      0x25d3 => $y <= 0,
      0x25d4 => $x >= 0 && $y <= 0,
      0x25d5 => $x >= 0 || $y >= 0,
      0x25e9 => $x + $y <= 0,
      0x25ea => $x + $y >= 0,
      0x25eb => abs($x) < $step / 2,
      default => false,
    };
  }

  /** Apply cutouts and partial-fill rules for uncommon circle symbols. */
  private static function special(int $code, float $x, float $y, bool $inside, bool $edge, bool $on): bool {
    if ($code === 0x25cc) {
      return $edge && ((int)floor((atan2($y, $x) + M_PI) * 8 / M_PI) % 2 === 0);
    }
    if ($code >= 0x25d6 && $code <= 0x25d7) {
      return $inside && ($code === 0x25d6 ? $x <= 0 : $x >= 0);
    }
    if ($code >= 0x25d8 && $code <= 0x25db) {
      $radius = hypot($x, $y);
      $on = $code === 0x25d8 ? $radius > 0.4 : ($radius > 0.85 || $radius < 0.45);
      return match ($code) {
        0x25da => $on && $y <= 0,
        0x25db => $on && $y >= 0,
        default => $on,
      };
    }
    if ($code >= 0x25dc && $code <= 0x25e1) {
      return $edge && match ($code) {
        0x25dc => $x <= 0 && $y <= 0,
        0x25dd => $x >= 0 && $y <= 0,
        0x25de => $x >= 0 && $y >= 0,
        0x25df => $x <= 0 && $y >= 0,
        0x25e0 => $y <= 0,
        default => $y >= 0,
      };
    }
    return $on;
  }

}
