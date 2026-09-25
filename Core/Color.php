<?php

namespace SPTK\Core;

/** Immutable RGB color shared safely by cells. */
final readonly class Color {

  public function __construct(public int $r, public int $g, public int $b) {
    foreach ([$r, $g, $b] as $channel) {
      if ($channel < 0 || $channel > 255) {
        throw new \InvalidArgumentException('Color channels must be between 0 and 255.');
      }
    }
  }

  public static function from(string $value): self {
    if (preg_match('/^#?([0-9a-f]{6})$/iD', trim($value), $match) !== 1) {
      throw new \InvalidArgumentException("Invalid RGB color: {$value}; expected #RRGGBB.");
    }
    $rgb = hexdec($match[1]);
    return new self(($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
  }

}
