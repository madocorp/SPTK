<?php

namespace SPTK\Widgets\Graph;

use SPTK\Rendering\FontFinder;

/** Measures FreeType graph text and clips each label to its allocated rectangle. */
final class Labels {

  private string $font;
  private array $metrics = [];

  /** Resolve the graph font once for the current painter. */
  public function __construct() {
    $this->font = FontFinder::find('LiberationMono-Bold');
  }

  /** Find a font size whose ink fits the desired pixel height. */
  public function size(int $height): float {
    $size = max(1.0, $height * 0.75);
    for ($i = 0; $i < 4; $i++) {
      $size *= $height / max(1, $this->measure('Ag', $size)[1]);
    }
    return $size;
  }

  /** Measure ink width, height, and bearing, caching repeated labels. */
  public function measure(string $text, float $size): array {
    $key = $size . ':' . $text;
    if (isset($this->metrics[$key])) {
      return $this->metrics[$key];
    }
    if ($text === '') {
      return [0, 0, 0, 0];
    }
    $box = imagettfbbox($size, 0, $this->font, $text);
    $xs = [$box[0], $box[2], $box[4], $box[6]];
    $ys = [$box[1], $box[3], $box[5], $box[7]];
    return $this->metrics[$key] = [max($xs) - min($xs), max($ys) - min($ys), min($xs), min($ys)];
  }

  /** Measure a shared line height and baseline including descenders and raster rounding. */
  public function line(array $texts, float $size): array {
    $top = 0;
    $bottom = 0;
    foreach (['Ag', ...$texts] as $text) {
      [, $height, , $bearing] = $this->measure($text, $size);
      $top = min($top, $bearing);
      $bottom = max($bottom, $bearing + $height);
    }
    return [$bottom - $top + 5, 2 - $top];
  }

  /** Draw a label using a common baseline and restore the full canvas clip. */
  public function paint(\GdImage $canvas, string $text, int $x, int $y, float $size, int $color, int $right, array $line): void {
    if ($text === '' || $x >= $right || $y >= imagesy($canvas)) {
      return;
    }
    $bounds = $this->measure($text, $size);
    imagesetclip($canvas, max(0, $x), max(0, $y), min(imagesx($canvas) - 1, $right - 1), min(imagesy($canvas) - 1, $y + $line[0] - 1));
    imagettftext($canvas, $size, 0, $x - $bounds[2], $y + $line[1], $color, $this->font, $text);
    imagesetclip($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1);
  }

}
