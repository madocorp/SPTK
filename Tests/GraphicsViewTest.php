<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\ElementContext;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Core\Texture;
use SPTK\Core\TextureRenderTarget;
use SPTK\Widgets\GraphicsView;

class GraphicsPlainTarget implements RenderTarget {

  public array $fills = [];

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
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

}

class GraphicsTextureProbe implements Texture {

  public array $ops = [];

  public function __construct(
    protected int $width,
    protected int $height
  ) {
  }

  public function width(): int {
    return $this->width;
  }

  public function height(): int {
    return $this->height;
  }

  public function clear(Color|string|int $color = 'transparent'): void {
    $this->ops[] = ['clear', $color instanceof Color ? $color->hex(true) : $color];
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $this->ops[] = ['line', $x1, $y1, $x2, $y2, $thickness];
  }

  public function drawRect(int $x, int $y, int $width, int $height, Color|string|int $color, int $thickness = 1): void {
    $this->ops[] = ['rect', $x, $y, $width, $height, $thickness];
  }

  public function fillRect(int $x, int $y, int $width, int $height, Color|string|int $color): void {
    $this->ops[] = ['fill', $x, $y, $width, $height];
  }

  public function copyTo(Texture $target, int $x, int $y): void {
    $this->ops[] = ['copyTo', $x, $y];
  }

  public function copy(Texture $target, int $sourceX, int $sourceY, int $targetX, int $targetY, int $width, int $height): void {
    $this->ops[] = ['copy', $sourceX, $sourceY, $targetX, $targetY, $width, $height];
  }

}

class GraphicsTargetProbe extends GraphicsPlainTarget implements TextureRenderTarget, SurfaceRenderTarget {

  public array $pixelRects = [];
  public array $created = [];
  protected array $stack = [];

  public function __construct(
    protected Rect $surface = new Rect(0, 0, 200, 100),
    protected int $cellWidth = 10,
    protected int $cellHeight = 20
  ) {
  }

  public function createTexture(int $width, int $height, Color|string|int $background = 'transparent'): Texture {
    $texture = new GraphicsTextureProbe($width, $height);
    $this->created[] = [$width, $height, $background, $texture];
    return $texture;
  }

  public function textureForPixels(Rect $pixelRect): Texture {
    $this->pixelRects[] = [$pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height];
    return new GraphicsTextureProbe($pixelRect->width, $pixelRect->height);
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

}

return [
  'graphics view fills fallback background on plain target' => function(): void {
    $called = false;
    $view = new GraphicsView('graphics', function() use (&$called): void {
      $called = true;
    });
    $view->setFrame(new Rect(2, 3, 4, 5));
    $target = new GraphicsPlainTarget();
    $view->render($target);
    assertSame([[2, 3, 4, 5]], $target->fills, 'GraphicsView should paint its normal cell background.');
    assertSame(false, $called, 'GraphicsView should skip painters when the target cannot create textures.');
  },

  'graphics view gives painter the cell pixel rectangle' => function(): void {
    $seen = null;
    $view = new GraphicsView('graphics', function(Texture $canvas, TextureRenderTarget $target, GraphicsView $self) use (&$seen): void {
      $seen = [$canvas->width(), $canvas->height(), $self->name(), $target instanceof GraphicsTargetProbe];
      $canvas->fillRect(1, 2, 3, 4, '#ffffff');
    });
    $view->setFrame(new Rect(1, 2, 5, 3));
    $target = new GraphicsTargetProbe();
    $view->render($target);
    assertSame([[10, 40, 50, 60]], $target->pixelRects, 'GraphicsView should translate its frame to pixels.');
    assertSame([50, 60, 'graphics', true], $seen, 'GraphicsView should pass a local canvas, target, and itself to the painter.');
  },

  'graphics view uses full pixel surface when it owns the surface' => function(): void {
    $seen = null;
    $view = new GraphicsView('graphics', function(Texture $canvas) use (&$seen): void {
      $seen = [$canvas->width(), $canvas->height()];
    });
    $view->setFrame(new Rect(0, 0, 12, 3));
    $target = new GraphicsTargetProbe(new Rect(5, 7, 123, 77), 10, 20);
    $view->render($target);
    assertSame([[5, 7, 123, 77]], $target->pixelRects, 'Owned pixel surface should use the whole available surface.');
    assertSame([123, 77], $seen, 'Painter canvas should match the owned surface.');
  },

  'graphics view redraw invalidates render' => function(): void {
    $view = new GraphicsView('graphics');
    $context = new ElementContext();
    $view->setContext($context);
    $context->clearRender();
    $view->redraw();
    assertSame(true, $context->renderDirty(), 'GraphicsView redraw should mark the tree render dirty.');
  },

  'graphics texture probe records copy operations' => function(): void {
    $source = new GraphicsTextureProbe(20, 10);
    $target = new GraphicsTextureProbe(8, 8);
    $source->copy($target, 2, 1, -3, 4, 12, 5);
    assertSame([['copy', 2, 1, -3, 4, 12, 5]], $source->ops, 'Probe texture should expose copy calls for callback tests.');
  },
];
