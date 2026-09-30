<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Core\RasterImage;

/** Paints wrapped rich text, inline backgrounds, padding, and borders into a tile-sized raster. */
final class Raster {

  private Fonts $fonts;

  /** Create the shared metric provider for this raster operation. */
  public function __construct() {
    $this->fonts = new Fonts();
  }

  /** Measure the complete height including borders and padding. */
  public function contentHeight(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): int {
    [$lines, $padding, $border, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
    return $this->height($lines, $gap) + $padding['top'] + $padding['bottom'] + $border['top'] + $border['bottom'];
  }

  /** Render at exact pixel dimensions, clipping all ink to the tile's content rectangle. */
  public function render(array $runs, array $style, int $width, int $height, int $referenceWidth, int $referenceHeight): RasterImage {
    if ($width < 1 || $height < 1) {
      throw new \InvalidArgumentException('Styled text raster dimensions must be positive.');
    }
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, $this->color($canvas, $style['background']));
    imagealphablending($canvas, true);
    try {
      [$lines, $padding, $border, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
      $this->border($canvas, $border, $style['borderColor']);
      $left = $padding['left'] + $border['left'];
      $top = $padding['top'] + $border['top'];
      $right = $width - $padding['right'] - $border['right'];
      $bottom = $height - $padding['bottom'] - $border['bottom'];
      if ($left < $right && $top < $bottom) {
        imagesetclip($canvas, $left, $top, $right - 1, $bottom - 1);
        $extra = max(0, $bottom - $top - $this->height($lines, $gap));
        $y = $top + match ($style['verticalAlign']) {
          'center' => intdiv($extra, 2), 'bottom' => $extra, default => 0,
        };
        foreach ($lines as $line) {
          if ($y >= $bottom) {
            break;
          }
          $this->line($canvas, $line, $style, $left, $right, $y);
          $y += $line['ascent'] + $line['descent'] + $gap;
        }
      }
      imagesetclip($canvas, 0, 0, $width - 1, $height - 1);
      return new RasterImage($canvas);
    } finally {
      imagedestroy($canvas);
    }
  }

  /** Resolve edge dimensions and wrap inside the remaining horizontal content space. */
  private function layout(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): array {
    $padding = Format::edges($style['padding'], $referenceWidth, $referenceHeight);
    $border = Format::edges($style['borderWidth'], $referenceWidth, $referenceHeight);
    $contentWidth = max(1, $width - $padding['left'] - $padding['right'] - $border['left'] - $border['right']);
    $lines = (new Lines($this->fonts))->layout($runs, $style, $contentWidth, $referenceWidth, $referenceHeight);
    return [$lines, $padding, $border, Format::dimension($style['lineGap'], $referenceWidth, $referenceHeight)];
  }

  /** Sum line metrics and the gaps between lines. */
  private function height(array $lines, int $gap): int {
    $height = max(0, count($lines) - 1) * $gap;
    foreach ($lines as $line) {
      $height += $line['ascent'] + $line['descent'];
    }
    return $height;
  }

  /** Paint each segment on the line's shared baseline with its own font and colors. */
  private function line(\GdImage $canvas, array $line, array $style, int $left, int $right, int $y): void {
    $extra = max(0, $right - $left - $line['width']);
    $x = $left + match ($style['textAlign']) {
      'center' => intdiv($extra, 2), 'right' => $extra, default => 0,
    };
    foreach ($line['segments'] as $segment) {
      $runStyle = $segment['style'];
      if ($runStyle['background'] !== 'transparent' && $segment['width'] > 0) {
        imagefilledrectangle($canvas, $x, $y, $x + $segment['width'] - 1, $y + $line['ascent'] + $line['descent'] - 1, $this->color($canvas, $runStyle['background']));
      }
      imagettftext($canvas, $segment['face'][1], 0, $x, $y + $line['ascent'], $this->color($canvas, $runStyle['color']), $segment['face'][0], $segment['text']);
      $x += $segment['width'];
    }
  }

  /** Draw independent edge borders before clipping the text interior. */
  private function border(\GdImage $canvas, array $border, string $color): void {
    $width = imagesx($canvas);
    $height = imagesy($canvas);
    $rects = [
      'top' => [0, 0, $width - 1, $border['top'] - 1],
      'right' => [$width - $border['right'], 0, $width - 1, $height - 1],
      'bottom' => [0, $height - $border['bottom'], $width - 1, $height - 1],
      'left' => [0, 0, $border['left'] - 1, $height - 1],
    ];
    foreach ($rects as $edge => $rect) {
      if ($border[$edge] > 0) {
        imagefilledrectangle($canvas, ...[...$rect, $this->color($canvas, $color)]);
      }
    }
  }

  /** Allocate a hexadecimal color including its transparency. */
  private function color(\GdImage $canvas, string $color): int {
    return imagecolorallocatealpha($canvas, ...Format::color($color));
  }

}
