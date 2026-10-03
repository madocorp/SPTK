<?php

namespace SPTK\Widgets\ColorSelector;

use SPTK\Core\Color;
use SPTK\SDLWrapper\SDL;

/** Builds the rainbow, tone, and exact-color rows and tracks their selection. */
final class Palette {

  public const COLUMNS = 16;

  private array $baseColors;
  private array $tones = [];
  private array $exactColors = [];
  private string $freeColor = '#000000';
  private string $value = '#ff0000';
  private int $baseColumn = 0;
  private int $row = 0;
  private int $column = 0;

  /** Build the fixed rainbow and select the initial color. */
  public function __construct(string $value) {
    $this->baseColors = $this->buildBaseColors();
    $this->setValue($value);
  }

  /** Return a validated lowercase six-digit RGB value. */
  public static function normalize(string $value): string {
    if (preg_match('/^#?[0-9a-f]{6}$/iD', $value) !== 1) {
      throw new \InvalidArgumentException('ColorSelector requires six hexadecimal digits.');
    }
    return '#' . strtolower(ltrim($value, '#'));
  }

  /** Return the currently selected color. */
  public function value(): string {
    return $this->value;
  }

  /** Return the selected swatch as a zero-based flat index. */
  public function position(): int {
    return $this->row * self::COLUMNS + $this->column;
  }

  /** Report whether a swatch is the selected cursor. */
  public function selected(int $row, int $column): bool {
    return $this->row === $row && $this->column === $column;
  }

  /** Report whether a rainbow swatch anchors the tone row. */
  public function baseSelected(int $column): bool {
    return $this->baseColumn === $column && $this->row !== 2;
  }

  /** Select an exact color and align the cursor to a palette swatch. */
  public function setValue(string $value): void {
    $this->value = self::normalize($value);
    $shortcuts = $this->defaultExactColors();
    if (($index = array_search($this->value, $shortcuts, true)) !== false) {
      $this->row = 2;
      $this->column = (int)$index;
      $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
      $this->exactColors = $this->buildExactColors();
      return;
    }
    foreach ($this->baseColors as $baseColumn => $base) {
      $tones = $this->buildTones($base);
      if (($index = array_search($this->value, $tones, true)) !== false) {
        $this->baseColumn = $baseColumn;
        $this->tones = $tones;
        $this->row = 1;
        $this->column = (int)$index;
        $this->exactColors = $this->buildExactColors();
        return;
      }
    }
    if (($index = array_search($this->value, $this->baseColors, true)) !== false) {
      $this->baseColumn = (int)$index;
      $this->tones = $this->buildTones($this->baseColors[$index]);
      $this->row = 0;
      $this->column = (int)$index;
    } else {
      $this->baseColumn = $this->nearestColorIndex($this->value, $this->baseColors);
      $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
      $this->row = 2;
      $this->column = self::COLUMNS - 1;
    }
    $this->exactColors = $this->buildExactColors();
  }

  /** Move the swatch cursor and return whether navigation was recognized. */
  public function move(int $key): bool {
    $row = $this->row;
    $column = $this->column;
    switch ($key) {
      case SDL::KEY_LEFT: $column = max(0, $column - 1); break;
      case SDL::KEY_RIGHT: $column = min(self::COLUMNS - 1, $column + 1); break;
      case SDL::KEY_UP:
        if ($row === 1) {
          $column = $this->baseColumn;
        }
        $row = max(0, $row - 1);
        break;
      case SDL::KEY_DOWN:
        if ($row === 0) {
          $column = $this->matchingToneColumn($this->baseColors[$column]);
        }
        $row = min(2, $row + 1);
        break;
      case SDL::KEY_HOME: $column = 0; break;
      case SDL::KEY_END: $column = self::COLUMNS - 1; break;
      case SDL::KEY_PAGEUP: $row = 0; break;
      case SDL::KEY_PAGEDOWN: $row = 2; break;
      default: return false;
    }
    if ($row === $this->row && $column === $this->column) {
      return true;
    }
    $this->row = $row;
    $this->column = $column;
    if ($row === 0) {
      $this->baseColumn = $column;
      $this->tones = $this->buildTones($this->baseColors[$column]);
    }
    $this->value = $this->colorAt($row, $column);
    $this->exactColors = $this->buildExactColors();
    return true;
  }

  /** Return the color displayed by one palette swatch. */
  public function colorAt(int $row, int $column): string {
    return match ($row) {
      0 => $this->baseColors[$column],
      1 => $this->tones[$column],
      default => $this->exactColors[$column],
    };
  }

  /** Generate black followed by fifteen saturated rainbow hues. */
  private function buildBaseColors(): array {
    $colors = ['#000000'];
    for ($column = 1; $column < self::COLUMNS; $column++) {
      $colors[] = $this->hsvToHex(($column - 1) / (self::COLUMNS - 1), 0.9, 0.9);
    }
    return $colors;
  }

  /** Generate dark-to-light tones for a rainbow color. */
  private function buildTones(string $base): array {
    $color = Color::from($base);
    if ($color->r === 0 && $color->g === 0 && $color->b === 0) {
      $tones = [];
      for ($i = 0; $i < self::COLUMNS; $i++) {
        $value = (int)round($i / (self::COLUMNS - 1) * 255);
        $tones[] = sprintf('#%02x%02x%02x', $value, $value, $value);
      }
      return $tones;
    }
    $tones = [];
    for ($i = 0; $i < self::COLUMNS; $i++) {
      $factor = $i < 8 ? 0.35 + $i / 7 * 0.65 : ($i - 7) / 8 * 0.75;
      $r = $i < 8 ? $color->r * $factor : $color->r + (255 - $color->r) * $factor;
      $g = $i < 8 ? $color->g * $factor : $color->g + (255 - $color->g) * $factor;
      $b = $i < 8 ? $color->b * $factor : $color->b + (255 - $color->b) * $factor;
      $tones[] = sprintf('#%02x%02x%02x', (int)round($r), (int)round($g), (int)round($b));
    }
    return $tones;
  }

  /** Preserve a custom color in the last shortcut slot. */
  private function buildExactColors(): array {
    $shortcuts = $this->defaultExactColors();
    if (!in_array($this->value, $this->baseColors, true) && !in_array($this->value, $this->tones, true) && !in_array($this->value, $shortcuts, true)) {
      $this->freeColor = $this->value;
    }
    return [...$shortcuts, $this->freeColor];
  }

  /** Return the fifteen standard exact-color shortcuts. */
  private function defaultExactColors(): array {
    return ['#ff0000', '#ffff00', '#00ff00', '#00ffff', '#0000ff', '#ff00ff', '#ffffff', '#808080', '#800000', '#808000', '#008000', '#008080', '#000080', '#800080', '#c0c0c0'];
  }

  /** Find the tone most like its rainbow anchor. */
  private function matchingToneColumn(string $base): int {
    return $this->nearestColorIndex($base, $this->buildTones($base));
  }

  /** Find an exact match or the nearest RGB swatch. */
  private function nearestColorIndex(string $value, array $colors): int {
    if (($index = array_search($value, $colors, true)) !== false) {
      return (int)$index;
    }
    $target = Color::from($value);
    $best = 0;
    $bestDistance = PHP_INT_MAX;
    foreach ($colors as $index => $candidate) {
      $candidate = Color::from($candidate);
      $distance = ($target->r - $candidate->r) ** 2 + ($target->g - $candidate->g) ** 2 + ($target->b - $candidate->b) ** 2;
      if ($distance < $bestDistance) {
        $best = $index;
        $bestDistance = $distance;
      }
    }
    return $best;
  }

  /** Convert a hue, saturation, and brightness to an RGB hex value. */
  private function hsvToHex(float $h, float $s, float $v): string {
    $h *= 6;
    $i = (int)floor($h);
    $f = $h - $i;
    $p = $v * (1 - $s);
    $q = $v * (1 - $f * $s);
    $t = $v * (1 - (1 - $f) * $s);
    [$r, $g, $b] = match ($i % 6) {
      0 => [$v, $t, $p],
      1 => [$q, $v, $p],
      2 => [$p, $v, $t],
      3 => [$p, $q, $v],
      4 => [$t, $p, $v],
      default => [$v, $p, $q],
    };
    return sprintf('#%02x%02x%02x', (int)round($r * 255), (int)round($g * 255), (int)round($b * 255));
  }

}
