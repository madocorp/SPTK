<?php

namespace SPTK\Core;

/**
 * Represents an immutable RGBA color normalized from strings, integers, or colors.
 */
class Color {

  protected const NAMES = [
    'black' => '#000000',
    'white' => '#ffffff',
    'red' => '#ff0000',
    'green' => '#00ff00',
    'blue' => '#0000ff',
    'cyan' => '#00ffff',
    'magenta' => '#ff00ff',
    'yellow' => '#ffff00',
    'transparent' => '#00000000',
  ];

  public function __construct(
    public int $r,
    public int $g,
    public int $b,
    public int $a = 255
  ) {
    $this->r = self::clamp($this->r);
    $this->g = self::clamp($this->g);
    $this->b = self::clamp($this->b);
    $this->a = self::clamp($this->a);
  }

  public static function from(string|int|self $value): self {
    if ($value instanceof self) {
      return $value;
    }
    if (is_int($value)) {
      return self::fromInt($value);
    }
    return self::fromString($value);
  }

  public static function fromString(string $value): self {
    $value = strtolower(trim($value));
    $value = self::NAMES[$value] ?? $value;
    if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value, $match)) {
      throw new \InvalidArgumentException("Invalid color: {$value}");
    }
    $hex = $match[1];
    if (strlen($hex) === 3 || strlen($hex) === 4) {
      $expanded = '';
      foreach (str_split($hex) as $digit) {
        $expanded .= $digit . $digit;
      }
      $hex = $expanded;
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $a = strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) : 255;
    return new self($r, $g, $b, $a);
  }

  public static function fromInt(int $value): self {
    if ($value < 0 || $value > 0xffffffff) {
      throw new \InvalidArgumentException("Invalid packed color: {$value}");
    }
    if ($value <= 0xffffff) {
      return new self(($value >> 16) & 0xff, ($value >> 8) & 0xff, $value & 0xff);
    }
    return new self(($value >> 24) & 0xff, ($value >> 16) & 0xff, ($value >> 8) & 0xff, $value & 0xff);
  }

  public function packedRgba(): int {
    return ($this->r << 24) | ($this->g << 16) | ($this->b << 8) | $this->a;
  }

  public function hex(bool $includeAlpha = false): string {
    if ($includeAlpha || $this->a !== 255) {
      return sprintf('#%02x%02x%02x%02x', $this->r, $this->g, $this->b, $this->a);
    }
    return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
  }

  protected static function clamp(int $value): int {
    return max(0, min(255, $value));
  }

}
