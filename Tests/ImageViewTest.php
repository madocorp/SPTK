<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\ImageRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Widgets\ImageView;

class ImageTargetProbe implements ImageRenderTarget, SurfaceRenderTarget {

  public array $images = [];
  public array $fills = [];
  public array $writes = [];
  protected array $stack = [];

  public function __construct(
    protected Rect $surface = new Rect(0, 0, 200, 100),
    protected int $cellWidth = 10,
    protected int $cellHeight = 20
  ) {
  }

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->writes[] = [$x, $y, $text];
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fills[] = [$rect->x, $rect->y, $rect->width, $rect->height];
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fill($rect, $value, $fg, $bg, $flags);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
  }

  public function pushClip(Rect $rect): void {
  }

  public function popClip(): void {
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight + 2, $this->cellHeight));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function currentSurfacePixelRect(): Rect {
    return $this->surface;
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
    $this->stack[] = $this->surface;
    $this->surface = $pixelRect;
  }

  public function popSurface(): void {
    $this->surface = array_pop($this->stack);
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
  }

  public function pixelRectForCells(Rect $rect): Rect {
    return new Rect(
      $this->surface->x + $rect->x * $this->cellWidth,
      $this->surface->y + $rect->y * $this->cellHeight,
      $rect->width * $this->cellWidth,
      $rect->height * $this->cellHeight
    );
  }

  public function drawImagePixels(\GdImage $image, Rect $sourceRect, Rect $targetRect): void {
    $this->images[] = [
      'image' => [imagesx($image), imagesy($image)],
      'source' => [$sourceRect->x, $sourceRect->y, $sourceRect->width, $sourceRect->height],
      'target' => [$targetRect->x, $targetRect->y, $targetRect->width, $targetRect->height],
    ];
  }

}

class PlainRenderTargetProbe extends ImageTargetProbe implements RenderTarget {
}

function pngBytes(int $width, int $height): string {
  $image = imagecreatetruecolor($width, $height);
  imagealphablending($image, false);
  imagesavealpha($image, true);
  $color = imagecolorallocatealpha($image, 20, 80, 160, 0);
  imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);
  ob_start();
  imagepng($image);
  return (string)ob_get_clean();
}

return [
  'image view loads GD-supported byte data' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(40, 20));
    assertSame(40, $image->imageWidth(), 'ImageView should read intrinsic width from GD data.');
    assertSame(20, $image->imageHeight(), 'ImageView should read intrinsic height from GD data.');
  },

  'image view loads base64 data URI' => function(): void {
    $image = new ImageView('image');
    $image->setBase64('data:image/png;base64,' . base64_encode(pngBytes(12, 8)));
    assertSame(12, $image->imageWidth(), 'ImageView should load base64 data URI width.');
    assertSame(8, $image->imageHeight(), 'ImageView should load base64 data URI height.');
  },

  'image view calculates cell height from supplied width' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50))->setCellSize(10);
    assertSame(10, $image->preferredColumns(), 'Explicit cell width should become preferred columns.');
    assertSame(5, $image->preferredRows(), 'Missing cell height should be calculated from image ratio.');
  },

  'image view calculates cell width from supplied height' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50))->setCellSize(null, 4);
    assertSame(8, $image->preferredColumns(), 'Missing cell width should be calculated from image ratio.');
    assertSame(4, $image->preferredRows(), 'Explicit cell height should become preferred rows.');
  },

  'image view uses source rect for aspect ratio and drawing' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50))->setSourceRect(10, 5, 20, 20)->setCellSize(6);
    assertSame(6, $image->preferredRows(), 'Square source rect should produce square preferred cell size.');
    $image->setFrame(new Rect(1, 1, 6, 6));
    $target = new ImageTargetProbe();
    $image->render($target);
    assertSame([10, 5, 20, 20], $target->images[0]['source'], 'Image draw should use configured source rectangle.');
  },

  'image view contains image by default' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50));
    $image->setFrame(new Rect(0, 0, 10, 10));
    $target = new ImageTargetProbe();
    $image->render($target);
    assertSame([0, 75, 100, 50], $target->images[0]['target'], 'Contained image should keep ratio and center vertically.');
  },

  'image view stretches when both cell sizes are supplied' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50))->setCellSize(10, 10);
    $image->setFrame(new Rect(0, 0, 10, 10));
    $target = new ImageTargetProbe();
    $image->render($target);
    assertSame([0, 0, 100, 200], $target->images[0]['target'], 'Both cell dimensions should stretch to the full frame.');
  },

  'image view calculates missing pixel size from ratio' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50))->setPixelSize(80);
    assertSame(80, $image->resolvedPixelWidth(), 'Explicit pixel width should be reported.');
    assertSame(40, $image->resolvedPixelHeight(), 'Missing pixel height should be calculated from image ratio.');
    $image->setFrame(new Rect(0, 0, 10, 10));
    $target = new ImageTargetProbe();
    $image->render($target);
    assertSame([10, 80, 80, 40], $target->images[0]['target'], 'Pixel-sized image should render at calculated size centered in the allocated frame.');
  },

  'image view uses full pixel surface when it owns the surface' => function(): void {
    $image = new ImageView('image');
    $image->setBytes(pngBytes(100, 50));
    $image->setFrame(new Rect(0, 0, 12, 3));
    $target = new ImageTargetProbe(new Rect(5, 7, 123, 77), 10, 20);
    $image->render($target);
    assertSame([5, 14, 123, 62], $target->images[0]['target'], 'Owned pixel surface should use the full pixel rectangle before contain fitting.');
  },
];
