<?php

namespace SPTK\Widgets\Graph;

use SPTK\Core\{Color, RasterImage, Style};

/** Composes graph labels, axes, grid, legend, and series into an immutable raster. */
final class Raster {

  /** Generate an image exactly matching the widget's allocated pixel dimensions. */
  public function render(array $series, array $options, Style $style, int $width, int $height, int $rowHeight): RasterImage {
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
      throw new \RuntimeException('Graph requires PHP GD with FreeType support.');
    }
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, $this->color($canvas, $style->background));
    imageantialias($canvas, true);
    try {
      $this->paint($canvas, $series, $options, $style, $rowHeight);
      return new RasterImage($canvas);
    } finally {
      imagedestroy($canvas);
    }
  }

  /** Reserve text areas, reduce crowded ticks, and compose the graph. */
  private function paint(\GdImage $canvas, array $series, array $o, Style $style, int $rowHeight): void {
    $width = imagesx($canvas);
    $height = imagesy($canvas);
    $labels = new Labels();
    $titleSize = $labels->size($rowHeight);
    $labelSize = $labels->size(max(9, (int)round($rowHeight * 0.8)));
    $tickSize = $labels->size(max(8, min(14, (int)round($rowHeight * 0.65))));
    $titleLine = $labels->line([$o['title']], $titleSize);
    $labelLine = $labels->line([$o['xLabel'], $o['yLabel']], $labelSize);
    $titleHeight = $o['title'] === '' ? 0 : $titleLine[0] + 6;
    $labelHeight = $labelLine[0] + 6;
    $legend = ($o['legend'] ?? count($series) > 1) && $series !== [];
    $ranges = Axes::ranges($series, $o);
    for ($pass = 0; $pass < 2; $pass++) {
      for ($count = $o['tickCount']; $count >= 2; $count--) {
        $xticks = Axes::ticks($ranges['xMin'], $ranges['xMax'], $count, $o['xUnit']);
        $yticks = Axes::ticks($ranges['yMin'], $ranges['yMax'], $count, $o['yUnit']);
        $xWidths = $this->widths($labels, $xticks, $tickSize);
        $yWidths = $this->widths($labels, $yticks, $tickSize);
        $tickLine = $labels->line([...array_column($xticks, 1), ...array_column($yticks, 1), ...($legend ? array_column($series, 'name') : [])], $tickSize);
        $left = max(max($yWidths) + 10, (int)ceil($xWidths[0] / 2) + 6);
        $right = $width - max(6, (int)ceil($xWidths[count($xWidths) - 1] / 2) + 6) - 1;
        $top = 6 + $titleHeight + ($o['yLabel'] === '' ? 0 : $labelHeight) + (int)ceil($tickLine[0] / 2);
        $bottom = $height - 14 - $tickLine[0] - ($o['xLabel'] === '' ? 0 : $labelHeight) - ($legend ? $tickLine[0] + 6 : 0);
        $fits = $right - $left >= (max($xWidths) + 6) * (count($xticks) - 1) && $bottom - $top >= ($tickLine[0] + 6) * (count($yticks) - 1);
        if ($fits) {
          break;
        }
      }
      if ($fits) {
        break;
      }
      $tickSize = $labels->size(8);
    }
    if ($right - $left < 12 || $bottom - $top < 12) {
      return;
    }
    $rect = [$left, $top, $right, $bottom];
    $axis = $this->color($canvas, $style->separator);
    $fg = $this->color($canvas, $style->foreground);
    $muted = $this->color($canvas, $this->blend($style->foreground, $style->background, 0.3));
    $grid = $this->color($canvas, $this->blend($style->foreground, $style->background, 0.85));
    foreach ($xticks as $i => [$value, $text]) {
      $x = (int)round($left + ($right - $left) * Axes::ratio($value, $ranges['xMin'], $ranges['xMax']));
      if ($o['grid']) {
        imageline($canvas, $x, $top, $x, $bottom, $grid);
      }
      imageline($canvas, $x, $bottom, $x, $bottom + 3, $axis);
      if ($fits || $i === 0) {
        $labels->paint($canvas, $text, $x - intdiv($xWidths[$i], 2), $bottom + 6, $tickSize, $muted, $width, $tickLine);
      }
    }
    foreach ($yticks as $i => [$value, $text]) {
      $y = (int)round($bottom - ($bottom - $top) * Axes::ratio($value, $ranges['yMin'], $ranges['yMax']));
      if ($o['grid']) {
        imageline($canvas, $left, $y, $right, $y, $grid);
      }
      imageline($canvas, $left - 3, $y, $left, $y, $axis);
      if ($fits || $i === 0) {
        $labels->paint($canvas, $text, $left - 7 - $yWidths[$i], $y - intdiv($tickLine[0], 2), $tickSize, $muted, $left - 5, $tickLine);
      }
    }
    (new Plot())->paint($canvas, $series, $ranges, $rect, $rowHeight);
    imageline($canvas, $left, $top, $left, $bottom, $axis);
    imageline($canvas, $left, $bottom, $right, $bottom, $axis);
    $labels->paint($canvas, $o['title'], max(6, intdiv($width - $labels->measure($o['title'], $titleSize)[0], 2)), 6, $titleSize, $fg, $width - 6, $titleLine);
    $labels->paint($canvas, $o['yLabel'], $left, 6 + $titleHeight, $labelSize, $fg, $width - 6, $labelLine);
    $footer = $bottom + 12 + $tickLine[0];
    if ($o['xLabel'] !== '') {
      $labels->paint($canvas, $o['xLabel'], max(6, $right - $labels->measure($o['xLabel'], $labelSize)[0]), $footer, $labelSize, $fg, $right + 1, $labelLine);
      $footer += $labelHeight;
    }
    if ($legend) {
      $this->legend($canvas, $series, $labels, $left, $footer, $tickSize, $tickLine, $fg);
    }
  }

  /** Paint colored legend markers followed by clipped series names. */
  private function legend(\GdImage $canvas, array $series, Labels $labels, int $x, int $y, float $size, array $line, int $fg): void {
    $right = imagesx($canvas) - 6;
    foreach ($series as $item) {
      if ($x + 12 >= $right) {
        break;
      }
      $color = $this->color($canvas, Color::from($item['color']));
      $top = $y + max(0, intdiv($line[0] - 6, 2));
      imagefilledrectangle($canvas, $x, $top, $x + 7, $top + 5, $color);
      $labels->paint($canvas, $item['name'], $x + 12, $y, $size, $fg, $right, $line);
      $x += 24 + $labels->measure($item['name'], $size)[0];
    }
  }

  /** Measure tick label widths without anonymous callbacks. */
  private function widths(Labels $labels, array $ticks, float $size): array {
    $widths = [];
    foreach ($ticks as [, $text]) {
      $widths[] = $labels->measure($text, $size)[0];
    }
    return $widths;
  }

  /** Allocate a mad4 RGB color in the GD canvas. */
  private function color(\GdImage $canvas, Color $color): int {
    return imagecolorallocate($canvas, $color->r, $color->g, $color->b);
  }

  /** Blend foreground toward background for muted scales and grid lines. */
  private function blend(Color $fg, Color $bg, float $amount): Color {
    return new Color(
      (int)round($fg->r * (1 - $amount) + $bg->r * $amount),
      (int)round($fg->g * (1 - $amount) + $bg->g * $amount),
      (int)round($fg->b * (1 - $amount) + $bg->b * $amount),
    );
  }

}
