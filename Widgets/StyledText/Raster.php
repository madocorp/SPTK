<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Core\RasterImage;

/** Paints wrapped rich text, margins, padding, and borders into a tile-sized raster. */
final class Raster {

  private Fonts $fonts;

  /** Create the shared metric provider for this raster operation. */
  public function __construct() {
    $this->fonts = new Fonts();
  }

  /** Measure the complete height including margin, border, and padding. */
  public function contentHeight(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): int {
    [$lines, $margin, $padding, $border, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
    return $this->height($lines, $gap) + $margin['top'] + $margin['bottom'] + $padding['top'] + $padding['bottom'] + $border['top'] + $border['bottom'];
  }

  /** Render at exact pixel dimensions, clipping all ink to the tile's content rectangle. */
  public function render(array $runs, array $style, int $width, int $height, int $referenceWidth, int $referenceHeight): RasterImage {
    if ($width < 1 || $height < 1) {
      throw new \InvalidArgumentException('Styled text raster dimensions must be positive.');
    }
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, $this->color($canvas, 'transparent'));
    imagealphablending($canvas, true);
    try {
      [$lines, $margin, $padding, $border, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
      $box = [$margin['left'], $margin['top'], $width - $margin['right'], $height - $margin['bottom']];
      if ($box[0] < $box[2] && $box[1] < $box[3]) {
        imagefilledrectangle($canvas, $box[0], $box[1], $box[2] - 1, $box[3] - 1, $this->color($canvas, $style['background']));
        $this->border($canvas, $border, $style['borderColor'], $box);
      }
      $left = $box[0] + $padding['left'] + $border['left'];
      $top = $box[1] + $padding['top'] + $border['top'];
      $right = $box[2] - $padding['right'] - $border['right'];
      $bottom = $box[3] - $padding['bottom'] - $border['bottom'];
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
    $margin = Format::edges($style['margin'], $referenceWidth, $referenceHeight);
    $padding = Format::edges($style['padding'], $referenceWidth, $referenceHeight);
    $border = Format::edges($style['borderWidth'], $referenceWidth, $referenceHeight);
    $contentWidth = max(1, $width - $margin['left'] - $margin['right'] - $padding['left'] - $padding['right'] - $border['left'] - $border['right']);
    $lines = (new Lines($this->fonts))->layout($runs, $style, $contentWidth, $referenceWidth, $referenceHeight);
    return [$lines, $margin, $padding, $border, Format::dimension($style['lineGap'], $referenceWidth, $referenceHeight)];
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
  private function border(\GdImage $canvas, array $border, string $color, array $box): void {
    [$left, $top, $right, $bottom] = $box;
    $rects = [
      'top' => [$left, $top, $right - 1, min($bottom - 1, $top + $border['top'] - 1)],
      'right' => [max($left, $right - $border['right']), $top, $right - 1, $bottom - 1],
      'bottom' => [$left, max($top, $bottom - $border['bottom']), $right - 1, $bottom - 1],
      'left' => [$left, $top, min($right - 1, $left + $border['left'] - 1), $bottom - 1],
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
