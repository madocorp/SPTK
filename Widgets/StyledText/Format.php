<?php

namespace SPTK\Widgets\StyledText;

/** Validates rich text styles and resolves their relative pixel dimensions. */
final class Format {

  public const DEFAULTS = [
    'color' => '#ffffff', 'background' => 'transparent', 'fontFamily' => 'sans-serif',
    'fontSize' => 24, 'fontWeight' => 'normal', 'fontStyle' => 'normal',
    'textAlign' => 'left', 'verticalAlign' => 'top', 'lineGap' => 4,
    'margin' => 0, 'padding' => 0, 'borderWidth' => 0, 'borderColor' => '#ffffff',
    'bold' => false, 'italic' => false, 'wrap' => true,
  ];

  /** Normalize supported style properties without silently accepting misspellings. */
  public static function style(array $style): array {
    foreach ($style as $name => $value) {
      if (!array_key_exists($name, self::DEFAULTS)) {
        throw new \InvalidArgumentException('Unknown styled text property: ' . $name);
      }
      if (in_array($name, ['color', 'background', 'borderColor'], true)) {
        if (!is_string($value)) {
          throw new \InvalidArgumentException($name . ' must be a hexadecimal color string.');
        }
        self::color($value);
      } else if (in_array($name, ['bold', 'italic', 'wrap'], true) && !is_bool($value)) {
        throw new \InvalidArgumentException($name . ' must be boolean.');
      } else if ($name === 'textAlign' && !in_array($value, ['left', 'center', 'right'], true)) {
        throw new \InvalidArgumentException('Text alignment must be left, center, or right.');
      } else if ($name === 'verticalAlign' && !in_array($value, ['top', 'center', 'bottom'], true)) {
        throw new \InvalidArgumentException('Vertical alignment must be top, center, or bottom.');
      } else if ($name === 'fontWeight' && !in_array($value, ['normal', 'bold'], true)) {
        throw new \InvalidArgumentException('Font weight must be normal or bold.');
      } else if ($name === 'fontStyle' && !in_array($value, ['normal', 'italic'], true)) {
        throw new \InvalidArgumentException('Font style must be normal or italic.');
      } else if ($name === 'fontFamily') {
        $families = is_array($value) ? $value : [$value];
        if ($families === []) {
          throw new \InvalidArgumentException('Font family must not be empty.');
        }
        foreach ($families as $family) {
          if (!is_string($family) || trim($family) === '') {
            throw new \InvalidArgumentException('Font families must be nonempty strings.');
          }
        }
      } else if (in_array($name, ['fontSize', 'lineGap', 'margin', 'padding', 'borderWidth'], true)) {
        if (is_array($value) && (!in_array($name, ['margin', 'padding', 'borderWidth'], true) || array_diff(array_keys($value), ['top', 'right', 'bottom', 'left']) !== [])) {
          throw new \InvalidArgumentException('Only margin, padding, and borderWidth accept named edges.');
        }
        foreach (is_array($value) ? $value : [$value] as $dimension) {
          self::dimension($dimension, 100, 100);
        }
      }
    }
    return $style;
  }

  /** Normalize text runs while preserving explicit line breaks and inline overrides. */
  public static function runs(string|array $runs): array {
    if (is_string($runs)) {
      $runs = [['text' => $runs]];
    }
    $result = [];
    foreach ($runs as $run) {
      if (!is_array($run)) {
        throw new \InvalidArgumentException('Styled text runs must be arrays.');
      }
      if (($run['type'] ?? '') === 'br') {
        $result[] = ['type' => 'br'];
        continue;
      }
      $text = $run['text'] ?? '';
      if (!is_string($text) || !mb_check_encoding($text, 'UTF-8')) {
        throw new \InvalidArgumentException('Styled text requires UTF-8 strings.');
      }
      $style = $run;
      unset($style['text'], $style['type']);
      $result[] = ['text' => str_replace(["\r\n", "\r", "\t"], ["\n", "\n", '    '], $text), ...self::style($style)];
    }
    return $result;
  }

  /** Convert a pixel, vh, or vw dimension to a nonnegative pixel count. */
  public static function dimension(mixed $value, int $width, int $height): int {
    if (is_int($value) || is_float($value)) {
      $number = $value;
    } else if (is_string($value) && preg_match('/^(\d+(?:\.\d+)?)(vh|vw|px)?$/D', $value, $match)) {
      $number = (float)$match[1] * match ($match[2] ?? 'px') {
        'vh' => $height / 100, 'vw' => $width / 100, default => 1,
      };
    } else {
      throw new \InvalidArgumentException('Invalid styled text dimension.');
    }
    if (!is_finite((float)$number) || $number < 0) {
      throw new \InvalidArgumentException('Styled text dimensions must be finite and nonnegative.');
    }
    return (int)round($number);
  }

  /** Expand a scalar or named edge widths to top, right, bottom, and left pixels. */
  public static function edges(mixed $value, int $width, int $height): array {
    $edges = [];
    foreach (['top', 'right', 'bottom', 'left'] as $edge) {
      $edges[$edge] = self::dimension(is_array($value) ? ($value[$edge] ?? 0) : $value, $width, $height);
    }
    return $edges;
  }

  /** Decode short or full hexadecimal RGB/RGBA colors for GD. */
  public static function color(string $value): array {
    if ($value === 'transparent') {
      return [0, 0, 0, 127];
    }
    if (preg_match('/^#([0-9a-f]{3})$/iD', $value, $match)) {
      $value = '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
    }
    if (!preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/iD', $value, $match)) {
      throw new \InvalidArgumentException('Invalid styled text color: ' . $value);
    }
    $rgb = hexdec($match[1]);
    return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255, isset($match[2]) ? (int)round((255 - hexdec($match[2])) * 127 / 255) : 0];
  }

}
