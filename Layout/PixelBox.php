<?php

namespace SPTK\Layout;

use SPTK\Core\Color;

/** Resolves one pixel layout item's margin, border, and padding around its content. */
final readonly class PixelBox {

  public function __construct(
    public int|string|array $margin = 0,
    public int|string|array $borderWidth = 0,
    public int|string|array $padding = 0,
    public ?Color $background = null,
    public ?Color $borderColor = null,
  ) {
  }

  /** Return border, background, and inner rectangles, all inside the allocated tile. */
  public function areas(Tile $tile, int $viewportWidth, int $viewportHeight): array {
    $border = self::inset($tile, self::edges($this->margin, $viewportWidth, $viewportHeight));
    $background = self::inset($border, self::edges($this->borderWidth, $viewportWidth, $viewportHeight));
    $inner = self::inset($background, self::edges($this->padding, $viewportWidth, $viewportHeight));
    return [$border, $background, $inner];
  }

  /** Measure one dimension in pixels, using the pixel subtree as the viewport. */
  public static function dimension(int|string $value, int $viewportWidth, int $viewportHeight, int $percentageBase): int {
    if (is_int($value)) {
      $pixels = $value;
    } else if (preg_match('/^(\d+(?:\.\d+)?)(px|vw|vh|%)?$/D', $value, $match)) {
      $number = (float)$match[1];
      $scaled = $number * match ($match[2] ?? 'px') {
        'vw' => $viewportWidth / 100,
        'vh' => $viewportHeight / 100,
        '%' => $percentageBase / 100,
        default => 1,
      };
      if (!is_finite($scaled) || $scaled > PHP_INT_MAX) {
        throw new \InvalidArgumentException('Pixel box dimension is too large.');
      }
      $pixels = (int)round($scaled);
    } else {
      throw new \InvalidArgumentException('Invalid pixel box dimension.');
    }
    if ($pixels < 0) {
      throw new \InvalidArgumentException('Pixel box dimensions must be nonnegative.');
    }
    return $pixels;
  }

  /** Expand a scalar or named edges; percentages use viewport width as in CSS. */
  public static function edges(int|string|array $value, int $viewportWidth, int $viewportHeight): array {
    $result = [];
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
      $size = is_array($value) ? ($value[$side] ?? 0) : $value;
      $result[$side] = self::dimension($size, $viewportWidth, $viewportHeight, $viewportWidth);
    }
    return $result;
  }

  /** Parse one to four CSS-order edge sizes for programmatic or XML styles. */
  public static function parseEdges(string $value): array {
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    if (count($parts) < 1 || count($parts) > 4 || in_array('', $parts, true)) {
      throw new \InvalidArgumentException('Pixel box edges need one to four sizes.');
    }
    foreach ($parts as $part) {
      if (!preg_match('/^\d+(?:\.\d+)?(?:px|vw|vh|%)?$/D', $part)) {
        throw new \InvalidArgumentException('Invalid pixel box edge size.');
      }
    }
    $values = match (count($parts)) {
      1 => [$parts[0], $parts[0], $parts[0], $parts[0]],
      2 => [$parts[0], $parts[1], $parts[0], $parts[1]],
      3 => [$parts[0], $parts[1], $parts[2], $parts[1]],
      default => $parts,
    };
    return ['top' => $values[0], 'right' => $values[1], 'bottom' => $values[2], 'left' => $values[3]];
  }

  private static function inset(Tile $tile, array $edges): Tile {
    $left = min($tile->width, $edges['left']);
    $right = min($tile->width - $left, $edges['right']);
    $top = min($tile->height, $edges['top']);
    $bottom = min($tile->height - $top, $edges['bottom']);
    return new Tile($tile->x + $left, $tile->y + $top, $tile->width - $left - $right, $tile->height - $top - $bottom);
  }

}
