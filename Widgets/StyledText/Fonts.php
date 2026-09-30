<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Rendering\FontFinder;

/** Resolves rich text font faces and measures baseline metrics with FreeType. */
final class Fonts {

  private static array $paths = [];
  private array $metrics = [];

  /** Resolve a face and convert CSS pixel font sizes to FreeType points. */
  public function face(array $style, int $width, int $height): array {
    $family = $style['fontFamily'];
    $family = is_array($family) ? implode(',', $family) : (string)$family;
    $weight = ($style['bold'] || $style['fontWeight'] === 'bold') ? 'bold' : 'regular';
    $slant = ($style['italic'] || $style['fontStyle'] === 'italic') ? 'italic' : 'roman';
    $key = $family . ':' . $weight . ':' . $slant;
    if (!isset(self::$paths[$key])) {
      self::$paths[$key] = FontFinder::find(is_file($family) ? $family : $family . ':weight=' . $weight . ':slant=' . $slant);
    }
    return [self::$paths[$key], max(1, Format::dimension($style['fontSize'], $width, $height)) * 0.75];
  }

  /** Measure the ink extents and advance of a string, retaining repeated measurements. */
  public function measure(string $text, array $face): array {
    $key = implode(':', $face) . ':' . $text;
    if (isset($this->metrics[$key])) {
      return $this->metrics[$key];
    }
    $bounds = imagettfbbox($face[1], 0, $face[0], $text === '' ? ' ' : $text);
    $left = min($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
    $right = max($bounds[0], $bounds[2], $bounds[4], $bounds[6]);
    $top = min($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
    $bottom = max($bounds[1], $bounds[3], $bounds[5], $bounds[7]);
    return $this->metrics[$key] = [max(0, $right - min(0, $left)), max(0, -$top), max(0, $bottom)];
  }

}
