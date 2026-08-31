<?php

namespace SPTK\Renderer;

use SPTK\Core\Color;
use SPTK\Core\Cell;
use SPTK\Core\ImageRenderTarget;
use SPTK\Core\PixelTextRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Core\Texture;
use SPTK\Core\TextureRenderTarget;
use SPTK\Runtime\SdlFont;
use SPTK\SDLWrapper\SDL;
use SPTK\SDLWrapper\TTF;

/**
 * Draws ordered grid commands to an SDL renderer using a cached glyph atlas.
 */
class SdlGridRenderer implements SurfaceRenderTarget, ImageRenderTarget, PixelTextRenderTarget, TextureRenderTarget {

  protected const VERTICAL_EDGE_TRIM = 2;

  protected SdlGlyphAtlas $atlas;
  protected $sourceRect;
  protected $sourceRectAddr;
  protected $targetRect;
  protected $targetRectAddr;
  protected $clipRect;
  protected $clipRectAddr;
  protected array $clips = [];
  protected int $windowWidth = 0;
  protected int $windowHeight = 0;
  protected int $columns = 0;
  protected int $rows = 0;
  protected int $offsetX = 0;
  protected int $offsetY = 0;
  protected int $gridPixelWidth = 0;
  protected int $gridPixelHeight = 0;
  protected int $surfaceX = 0;
  protected int $surfaceY = 0;
  protected array $surfaces = [];
  protected array $textures = [];
  protected array $fonts = [];

  public function __construct(
    protected SDL $sdl,
    protected TTF $ttf,
    protected mixed $renderer,
    protected SdlFont $font
  ) {
    $this->atlas = new SdlGlyphAtlas($sdl, $ttf, $renderer, $font);
    $this->sourceRect = $this->sdl->ffi->new('SDL_FRect');
    $this->sourceRectAddr = \FFI::addr($this->sourceRect);
    $this->targetRect = $this->sdl->ffi->new('SDL_FRect');
    $this->targetRectAddr = \FFI::addr($this->targetRect);
    $this->clipRect = $this->sdl->ffi->new('SDL_Rect');
    $this->clipRectAddr = \FFI::addr($this->clipRect);
  }

  public function cellWidth(): int {
    return $this->font->letterWidth;
  }

  public function cellHeight(): int {
    return $this->font->rowHeight;
  }

  public function rowsForHeight(int $height): int {
    return max(1, intdiv($height + self::VERTICAL_EDGE_TRIM, $this->font->rowHeight));
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->font->letterWidth));
  }

  public function scrollbarThickness(string $orientation): float {
    if ($orientation === 'horizontal') {
      return $this->font->letterWidth / max(1, $this->font->rowHeight);
    }
    return 1.0;
  }

  public function beginFrame(int $windowWidth, int $windowHeight, int $columns, int $rows, Color|string|int|null $background = null): void {
    $this->windowWidth = $windowWidth;
    $this->windowHeight = $windowHeight;
    $this->columns = $columns;
    $this->rows = $rows;
    $this->gridPixelWidth = $columns * $this->font->letterWidth;
    $this->gridPixelHeight = max(1, $rows * $this->font->rowHeight - self::VERTICAL_EDGE_TRIM);
    $this->offsetX = max(0, intdiv($windowWidth - $this->gridPixelWidth, 2));
    $this->offsetY = 0;
    $this->surfaceX = 0;
    $this->surfaceY = 0;
    $this->surfaces = [];
    $this->clips = [];
    $this->applyClip(null);
    $this->clear($background);
  }

  public function clear(Color|string|int|null $color = null): void {
    $color = Color::from($color ?? '#000000');
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $this->sdl->ffi->SDL_RenderClear($this->renderer);
  }

  public function present(): void {
    $this->sdl->ffi->SDL_RenderPresent($this->renderer);
  }

  public function currentSurfacePixelRect(): Rect {
    return new Rect($this->surfaceX, $this->surfaceY, $this->windowWidth, $this->windowHeight);
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
    $this->surfaces[] = [
      $this->windowWidth,
      $this->windowHeight,
      $this->columns,
      $this->rows,
      $this->offsetX,
      $this->offsetY,
      $this->gridPixelWidth,
      $this->gridPixelHeight,
      $this->surfaceX,
      $this->surfaceY,
      $this->clips,
    ];
    $this->windowWidth = $pixelRect->width;
    $this->windowHeight = $pixelRect->height;
    $this->columns = $columns;
    $this->rows = $rows;
    $this->gridPixelWidth = $columns * $this->font->letterWidth;
    $this->gridPixelHeight = max(1, $rows * $this->font->rowHeight - self::VERTICAL_EDGE_TRIM);
    $this->surfaceX = $pixelRect->x;
    $this->surfaceY = $pixelRect->y;
    $extraX = max(0, $pixelRect->width - $this->gridPixelWidth);
    $extraY = max(0, $pixelRect->height - $this->gridPixelHeight);
    $alignX = str_contains($gridAlignment, 'left') ? 0 : intdiv($extraX, 2);
    $alignY = str_contains($gridAlignment, 'top') || $pixelRect->height === $rows * $this->font->rowHeight ? 0 : intdiv($extraY, 2);
    $this->offsetX = $pixelRect->x + $alignX;
    $this->offsetY = $pixelRect->y + $alignY;
    $this->clips = [];
    $this->applyClip(null);
    if ($background !== null) {
      $this->fillPixelRect($pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, Color::from($background));
    }
  }

  public function popSurface(): void {
    $state = array_pop($this->surfaces);
    if ($state === null) {
      return;
    }
    [
      $this->windowWidth,
      $this->windowHeight,
      $this->columns,
      $this->rows,
      $this->offsetX,
      $this->offsetY,
      $this->gridPixelWidth,
      $this->gridPixelHeight,
      $this->surfaceX,
      $this->surfaceY,
      $this->clips,
    ] = $state;
    $clip = $this->clips[count($this->clips) - 1] ?? null;
    $this->applyClip($clip);
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
    if ($this->pixelRectCoversSurface($pixelRect)) {
      $clip = $this->clips[count($this->clips) - 1] ?? null;
      $this->applyClip(null);
      $this->fillPixelRect($pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, Color::from($color));
      $this->applyClip($clip);
      return;
    }
    $this->fillPixelRect($pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, Color::from($color));
  }

  public function pixelRectForCells(Rect $rect): Rect {
    return new Rect(
      $this->offsetX + $rect->x * $this->font->letterWidth,
      $this->offsetY + $rect->y * $this->font->rowHeight,
      $rect->width * $this->font->letterWidth,
      $rect->height * $this->font->rowHeight
    );
  }

  public function drawImagePixels(\GdImage $image, Rect $sourceRect, Rect $targetRect): void {
    if ($sourceRect->width <= 0 || $sourceRect->height <= 0 || $targetRect->width <= 0 || $targetRect->height <= 0) {
      return;
    }
    $texture = $this->textureForImage($image);
    if ($texture === null) {
      return;
    }
    $this->sourceRect->x = (float)$sourceRect->x;
    $this->sourceRect->y = (float)$sourceRect->y;
    $this->sourceRect->w = (float)$sourceRect->width;
    $this->sourceRect->h = (float)$sourceRect->height;
    $this->targetRect->x = (float)$targetRect->x;
    $this->targetRect->y = (float)$targetRect->y;
    $this->targetRect->w = (float)$targetRect->width;
    $this->targetRect->h = (float)$targetRect->height;
    $this->sdl->ffi->SDL_RenderTexture($this->renderer, $texture, $this->sourceRectAddr, $this->targetRectAddr);
    $this->sdl->ffi->SDL_DestroyTexture($texture);
  }

  public function createTexture(int $width, int $height, Color|string|int $background = 'transparent'): Texture {
    $texture = SdlTexture::create($this->sdl, $this->renderer, $width, $height, $background, $this->restoreWindowTarget(...));
    $this->textures[] = $texture;
    return $texture;
  }

  public function textureForPixels(Rect $pixelRect): Texture {
    return SdlTexture::view($this->sdl, $this->renderer, $pixelRect, $this->restoreWindowTarget(...));
  }

  public function measureTextPixels(string $text, array $font = []): array {
    if ($this->usesGridFont($font)) {
      return [mb_strlen($text) * $this->font->letterWidth, $this->font->height];
    }
    if ($text === '') {
      return [0, $this->fontHeight($font)];
    }
    $handle = $this->fontHandle($font);
    $color = $this->ttf->ffi->new('SDL_Color');
    $color->r = 255;
    $color->g = 255;
    $color->b = 255;
    $color->a = 255;
    $surface = $this->ttf->ffi->TTF_RenderText_Blended($handle, $text, strlen($text), $color);
    if ($surface === null) {
      return [mb_strlen($text) * $this->font->letterWidth, $this->fontHeight($font)];
    }
    $width = (int)$surface->w;
    $height = (int)$surface->h;
    $this->ttf->ffi->SDL_DestroySurface($surface);
    return [$width, max(1, $height)];
  }

  public function fontMetricsPixels(array $font = []): array {
    if ($this->usesGridFont($font)) {
      $ascent = max(0, $this->font->ascent);
      $descent = abs($this->font->descent);
      return ['ascent' => $ascent, 'descent' => $descent, 'height' => max(1, $this->font->height)];
    }
    $handle = $this->fontHandle($font);
    $ascent = max(0, (int)$this->ttf->ffi->TTF_GetFontAscent($handle));
    $descent = abs((int)$this->ttf->ffi->TTF_GetFontDescent($handle));
    $height = max(1, (int)$this->ttf->ffi->TTF_GetFontHeight($handle));
    return ['ascent' => $ascent, 'descent' => $descent, 'height' => $height];
  }

  public function drawTextPixels(string $text, Rect $targetRect, Color|string|int $color, array $font = []): void {
    if ($text === '' || $targetRect->width <= 0 || $targetRect->height <= 0) {
      return;
    }
    if ($this->usesGridFont($font)) {
      $this->drawAtlasTextPixels($text, $targetRect, Color::from($color));
      return;
    }
    $handle = $this->fontHandle($font);
    $color = Color::from($color);
    $sdlColor = $this->ttf->ffi->new('SDL_Color');
    $sdlColor->r = $color->r;
    $sdlColor->g = $color->g;
    $sdlColor->b = $color->b;
    $sdlColor->a = $color->a;
    $surface = $this->ttf->ffi->TTF_RenderText_Blended($handle, $text, strlen($text), $sdlColor);
    if ($surface === null) {
      return;
    }
    $surface2 = \FFI::cast($this->sdl->ffi->type('SDL_Surface*'), $surface);
    $texture = $this->sdl->ffi->SDL_CreateTextureFromSurface($this->renderer, $surface2);
    if ($texture === null) {
      $this->ttf->ffi->SDL_DestroySurface($surface);
      return;
    }
    $this->sdl->ffi->SDL_SetTextureBlendMode($texture, SDL::SDL_BLENDMODE_BLEND);
    $this->targetRect->x = (float)$targetRect->x;
    $this->targetRect->y = (float)$targetRect->y;
    $this->targetRect->w = (float)$targetRect->width;
    $this->targetRect->h = (float)$targetRect->height;
    $this->sdl->ffi->SDL_RenderTexture($this->renderer, $texture, null, $this->targetRectAddr);
    $this->sdl->ffi->SDL_DestroyTexture($texture);
    $this->ttf->ffi->SDL_DestroySurface($surface);
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $color = Color::from($color);
    if (abs($y1 - $y2) < 0.01) {
      $this->fillPixelRect((int)round(min($x1, $x2)), (int)round($y1) - intdiv($thickness, 2), (int)round(abs($x2 - $x1)), $thickness, $color);
      return;
    }
    if (abs($x1 - $x2) < 0.01) {
      $this->fillPixelRect((int)round($x1) - intdiv($thickness, 2), (int)round(min($y1, $y2)), $thickness, (int)round(abs($y2 - $y1)), $color);
      return;
    }
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $this->sdl->ffi->SDL_RenderLine($this->renderer, (float)$x1, (float)$y1, (float)$x2, (float)$y2);
  }

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $cell = $value instanceof Cell ? $value : new Cell($value, $fg, $bg, $flags);
    $this->fillPixelRect($this->offsetX + $x * $this->font->letterWidth, $this->offsetY + $y * $this->font->rowHeight, $this->font->letterWidth, $this->font->rowHeight, $cell->bg);
    if ($cell->glyph !== ' ') {
      $this->drawGlyph($cell->glyph, $x, $y, $cell->fg);
    }
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
      $chars = str_split($text);
    }
    foreach ($chars as $i => $char) {
      $this->put($x + $i, $y, $char, $fg, $bg, $flags);
    }
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $cell = $value instanceof Cell ? $value : new Cell($value, $fg, $bg, $flags);
    if ($this->rectCoversSurface($rect)) {
      $clip = $this->clips[count($this->clips) - 1] ?? null;
      $this->applyClip(null);
      $this->fillPixelRect($this->surfaceX, $this->surfaceY, $this->windowWidth, $this->windowHeight, $cell->bg);
      $this->applyClip($clip);
    } else {
      $this->fillGridRect($rect, $cell->bg);
    }
    if ($cell->glyph !== ' ') {
      for ($y = $rect->y; $y < $rect->bottom(); $y++) {
        for ($x = $rect->x; $x < $rect->right(); $x++) {
          $this->drawGlyph($cell->glyph, $x, $y, $cell->fg);
        }
      }
    }
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $cell = $value instanceof Cell ? $value : new Cell($value, $fg, $bg, $flags);
    $this->fill($rect, $cell);
    $clip = $this->clips[count($this->clips) - 1] ?? null;
    $this->applyClip(null);
    $pixelX = $this->offsetX + $rect->x * $this->font->letterWidth;
    $pixelY = $this->offsetY + $rect->y * $this->font->rowHeight;
    $pixelWidth = $rect->width * $this->font->letterWidth;
    $pixelHeight = $rect->height * $this->font->rowHeight;
    $rightEdge = $this->surfaceX + $this->windowWidth;
    if ($rect->x <= 0 && $this->offsetX > $this->surfaceX) {
      $this->fillPixelRect($this->surfaceX, $pixelY, $this->offsetX - $this->surfaceX, $pixelHeight, $cell->bg);
    }
    if ($rect->right() >= $this->columns && $this->offsetX + $this->gridPixelWidth < $rightEdge) {
      $this->fillPixelRect($this->offsetX + $this->gridPixelWidth, $pixelY, $rightEdge - $this->offsetX - $this->gridPixelWidth, $pixelHeight, $cell->bg);
    }
    if ($rect->y <= 0 && $this->offsetY > $this->surfaceY) {
      $this->fillPixelRect($pixelX, $this->surfaceY, $pixelWidth, $this->offsetY - $this->surfaceY, $cell->bg);
      if ($rect->x <= 0 && $this->offsetX > $this->surfaceX) {
        $this->fillPixelRect($this->surfaceX, $this->surfaceY, $this->offsetX - $this->surfaceX, $this->offsetY - $this->surfaceY, $cell->bg);
      }
      if ($rect->right() >= $this->columns && $this->offsetX + $this->gridPixelWidth < $rightEdge) {
        $this->fillPixelRect($this->offsetX + $this->gridPixelWidth, $this->surfaceY, $rightEdge - $this->offsetX - $this->gridPixelWidth, $this->offsetY - $this->surfaceY, $cell->bg);
      }
    }
    $this->applyClip($clip);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
    $color = Color::from($color);
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $px1 = $this->offsetX + $x1 * $this->font->letterWidth;
    $py1 = $this->offsetY + $y1 * $this->font->rowHeight;
    $px2 = $this->offsetX + $x2 * $this->font->letterWidth;
    $py2 = $this->offsetY + $y2 * $this->font->rowHeight;
    if (abs($py1 - $py2) < 0.01) {
      $this->fillPixelRect((int)round(min($px1, $px2)), (int)round($py1) - intdiv($thickness, 2), (int)round(abs($px2 - $px1)), $thickness, $color);
      return;
    }
    if (abs($px1 - $px2) < 0.01) {
      $this->fillPixelRect((int)round($px1) - intdiv($thickness, 2), (int)round(min($py1, $py2)), $thickness, (int)round(abs($py2 - $py1)), $color);
      return;
    }
    $this->sdl->ffi->SDL_RenderLine($this->renderer, (float)$px1, (float)$py1, (float)$px2, (float)$py2);
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    if ($rect->width <= 0 || $rect->height <= 0) {
      return;
    }
    $color = Color::from($color);
    $x = $this->offsetX + $rect->x * $this->font->letterWidth;
    $y = $this->offsetY + $rect->y * $this->font->rowHeight;
    $w = $rect->width * $this->font->letterWidth;
    $h = $rect->height * $this->font->rowHeight;
    $this->fillPixelRect($x, $y, $w, $thickness, $color);
    $this->fillPixelRect($x, $y + $h - $thickness, $w, $thickness, $color);
    $this->fillPixelRect($x, $y, $thickness, $h, $color);
    $this->fillPixelRect($x + $w - $thickness, $y, $thickness, $h, $color);
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    if ($rect->width <= 0 || $rect->height <= 0) {
      return;
    }
    $clip = $this->clips[count($this->clips) - 1] ?? null;
    $this->applyClip(null);
    $color = Color::from($color);
    $x = $this->offsetX + $rect->x * $this->font->letterWidth;
    $y = $this->offsetY + $rect->y * $this->font->rowHeight;
    $w = $rect->width * $this->font->letterWidth;
    $h = $rect->height * $this->font->rowHeight;
    $this->fillPixelRect($x - $thickness, $y - $thickness, $w + $thickness * 2, $thickness, $color);
    $this->fillPixelRect($x - $thickness, $y + $h, $w + $thickness * 2, $thickness, $color);
    $this->fillPixelRect($x - $thickness, $y, $thickness, $h, $color);
    $this->fillPixelRect($x + $w, $y, $thickness, $h, $color);
    $this->applyClip($clip);
  }

  public function drawFillTriangle(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, string|int|Color $color): void {
    $color = Color::from($color);
    $px1 = $this->offsetX + $x1 * $this->font->letterWidth;
    $py1 = $this->offsetY + $y1 * $this->font->rowHeight;
    $px2 = $this->offsetX + $x2 * $this->font->letterWidth;
    $py2 = $this->offsetY + $y2 * $this->font->rowHeight;
    $px3 = $this->offsetX + $x3 * $this->font->letterWidth;
    $py3 = $this->offsetY + $y3 * $this->font->rowHeight;
    $minY = (int)floor(min($py1, $py2, $py3));
    $maxY = (int)ceil(max($py1, $py2, $py3));
    if ($minY === $maxY) {
      return;
    }
    for ($y = $minY; $y < $maxY; $y++) {
      $lineY = $y + 0.5;
      $intersections = [];
      $this->addTriangleIntersection($intersections, $px1, $py1, $px2, $py2, $lineY);
      $this->addTriangleIntersection($intersections, $px2, $py2, $px3, $py3, $lineY);
      $this->addTriangleIntersection($intersections, $px3, $py3, $px1, $py1, $lineY);
      if (count($intersections) < 2) {
        continue;
      }
      sort($intersections);
      $startX = (int)floor($intersections[0]);
      $endX = (int)ceil($intersections[count($intersections) - 1]);
      if ($startX < $endX) {
        $this->fillPixelRect($startX, $y, $endX - $startX, 1, $color);
      }
    }
  }

  public function drawScrollbar(Rect $rect, string $orientation, int $offset, int $visible, int $total, string|int|Color $color): void {
    if ($visible <= 0 || $total <= $visible) {
      return;
    }
    $color = Color::from($color);
    if ($orientation === 'vertical') {
      $x1 = $this->offsetX + $rect->x * $this->font->letterWidth;
      $x2 = $this->offsetX + $rect->right() * $this->font->letterWidth;
      $y1 = $this->offsetY + $rect->y * $this->font->rowHeight;
      $y2 = $this->offsetY + $rect->bottom() * $this->font->rowHeight;
      $barHeight = max(1, $y2 - $y1);
      $handleY1 = $y1 + round($barHeight * $offset / $total);
      $handleHeight = max(1, round($barHeight * $visible / $total));
      $handleY2 = min($y2, $handleY1 + $handleHeight);
      $this->drawFillTrianglePixels($x1, $y1, $x2, $y1, $x2, $handleY1, $color);
      $this->drawFillTrianglePixels($x1, $y2, $x2, $y2, $x2, $handleY2, $color);
      return;
    }
    $x1 = $this->offsetX + $rect->x * $this->font->letterWidth;
    $x2 = $this->offsetX + $rect->right() * $this->font->letterWidth;
    $y2 = $this->offsetY + $rect->bottom() * $this->font->rowHeight;
    $y1 = $y2 - $this->font->letterWidth;
    $barWidth = max(1, $x2 - $x1);
    $handleX1 = $x1 + round($barWidth * $offset / $total);
    $handleWidth = max(1, round($barWidth * $visible / $total));
    $handleX2 = min($x2, $handleX1 + $handleWidth);
    $this->drawFillTrianglePixels($x1, $y1, $x1, $y2, $handleX1, $y2, $color);
    $this->drawFillTrianglePixels($x2, $y1, $x2, $y2, $handleX2, $y2, $color);
  }

  protected function drawFillTrianglePixels(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, Color $color): void {
    $minY = (int)floor(min($y1, $y2, $y3));
    $maxY = (int)ceil(max($y1, $y2, $y3));
    if ($minY === $maxY) {
      return;
    }
    for ($y = $minY; $y < $maxY; $y++) {
      $lineY = $y + 0.5;
      $intersections = [];
      $this->addTriangleIntersection($intersections, $x1, $y1, $x2, $y2, $lineY);
      $this->addTriangleIntersection($intersections, $x2, $y2, $x3, $y3, $lineY);
      $this->addTriangleIntersection($intersections, $x3, $y3, $x1, $y1, $lineY);
      if (count($intersections) < 2) {
        continue;
      }
      sort($intersections);
      $startX = (int)floor($intersections[0]);
      $endX = (int)ceil($intersections[count($intersections) - 1]);
      if ($startX < $endX) {
        $this->fillPixelRect($startX, $y, $endX - $startX, 1, $color);
      }
    }
  }

  protected function addTriangleIntersection(array &$intersections, float $x1, float $y1, float $x2, float $y2, float $lineY): void {
    if (abs($y1 - $y2) < 0.01) {
      return;
    }
    $minY = min($y1, $y2);
    $maxY = max($y1, $y2);
    if ($lineY < $minY || $lineY >= $maxY) {
      return;
    }
    $intersections[] = $x1 + ($lineY - $y1) * ($x2 - $x1) / ($y2 - $y1);
  }

  public function pushClip(Rect $rect): void {
    $this->clips[] = $rect;
    $this->applyClip($rect);
  }

  public function popClip(): void {
    array_pop($this->clips);
    $clip = $this->clips[count($this->clips) - 1] ?? null;
    $this->applyClip($clip);
  }

  protected function drawGlyph(string $glyph, int $x, int $y, Color $color): void {
    $texture = $this->atlas->texture();
    $this->sdl->ffi->SDL_SetTextureColorMod($texture, $color->r, $color->g, $color->b);
    $this->sdl->ffi->SDL_SetTextureAlphaMod($texture, $color->a);
    [$mapX, $mapY] = $this->atlas->map($glyph);
    $glyphOffsetY = intdiv(max(0, $this->font->rowHeight - $this->font->height), 2);
    $this->sourceRect->x = (float)$mapX - 1;
    $this->sourceRect->y = (float)$mapY - 1;
    $this->sourceRect->w = (float)$this->font->letterWidth + 2;
    $this->sourceRect->h = (float)$this->font->height + 2;
    $this->targetRect->x = (float)($this->offsetX + $x * $this->font->letterWidth) - 1;
    $this->targetRect->y = (float)($this->offsetY + $y * $this->font->rowHeight + $glyphOffsetY) - 1;
    $this->targetRect->w = (float)$this->font->letterWidth + 2;
    $this->targetRect->h = (float)$this->font->height + 2;
    $this->sdl->ffi->SDL_RenderTexture($this->renderer, $texture, $this->sourceRectAddr, $this->targetRectAddr);
  }

  protected function drawAtlasTextPixels(string $text, Rect $targetRect, Color $color): void {
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
      $chars = str_split($text);
    }
    $x = $targetRect->x;
    $y = $targetRect->y + intdiv(max(0, $targetRect->height - $this->font->height), 2);
    foreach ($chars as $char) {
      $this->drawGlyphPixels($char, $x, $y, $color);
      $x += $this->font->letterWidth;
      if ($x >= $targetRect->right()) {
        break;
      }
    }
  }

  protected function drawGlyphPixels(string $glyph, int $x, int $y, Color $color): void {
    $texture = $this->atlas->texture();
    $this->sdl->ffi->SDL_SetTextureColorMod($texture, $color->r, $color->g, $color->b);
    $this->sdl->ffi->SDL_SetTextureAlphaMod($texture, $color->a);
    [$mapX, $mapY] = $this->atlas->map($glyph);
    $this->sourceRect->x = (float)$mapX - 1;
    $this->sourceRect->y = (float)$mapY - 1;
    $this->sourceRect->w = (float)$this->font->letterWidth + 2;
    $this->sourceRect->h = (float)$this->font->height + 2;
    $this->targetRect->x = (float)$x - 1;
    $this->targetRect->y = (float)$y - 1;
    $this->targetRect->w = (float)$this->font->letterWidth + 2;
    $this->targetRect->h = (float)$this->font->height + 2;
    $this->sdl->ffi->SDL_RenderTexture($this->renderer, $texture, $this->sourceRectAddr, $this->targetRectAddr);
  }

  protected function fillPixelRect(int $x, int $y, int $width, int $height, Color $color): void {
    if ($width <= 0 || $height <= 0) {
      return;
    }
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $this->targetRect->x = (float)$x;
    $this->targetRect->y = (float)$y;
    $this->targetRect->w = (float)$width;
    $this->targetRect->h = (float)$height;
    $this->sdl->ffi->SDL_RenderFillRect($this->renderer, $this->targetRectAddr);
  }

  protected function fillGridRect(Rect $rect, Color $color): void {
    $this->fillPixelRect(
      $this->offsetX + $rect->x * $this->font->letterWidth,
      $this->offsetY + $rect->y * $this->font->rowHeight,
      $rect->width * $this->font->letterWidth,
      $rect->height * $this->font->rowHeight,
      $color
    );
  }

  protected function rectCoversSurface(Rect $rect): bool {
    return $rect->x <= 0
      && $rect->y <= 0
      && $rect->right() >= $this->columns
      && $rect->bottom() >= $this->rows;
  }

  protected function pixelRectCoversSurface(Rect $rect): bool {
    return $rect->x <= $this->surfaceX
      && $rect->y <= $this->surfaceY
      && $rect->right() >= $this->surfaceX + $this->windowWidth
      && $rect->bottom() >= $this->surfaceY + $this->windowHeight;
  }

  protected function textureForImage(\GdImage $image): mixed {
    $width = imagesx($image);
    $height = imagesy($image);
    if ($width <= 0 || $height <= 0) {
      return null;
    }
    $texture = $this->sdl->ffi->SDL_CreateTexture(
      $this->renderer,
      SDL::SDL_PIXELFORMAT_RGBA8888,
      SDL::SDL_TEXTUREACCESS_STATIC,
      $width,
      $height
    );
    if ($texture === null) {
      return null;
    }
    $pixels = \FFI::new('uint8_t[' . ($width * $height * 4) . ']');
    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        $c = imagecolorat($image, $x, $y);
        $a = 255 - intdiv((($c >> 24) & 0x7F) * 255, 127);
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;
        $pixels[$offset++] = $a;
        $pixels[$offset++] = $b;
        $pixels[$offset++] = $g;
        $pixels[$offset++] = $r;
      }
    }
    $this->sdl->ffi->SDL_UpdateTexture($texture, null, $pixels, $width * 4);
    $this->sdl->ffi->SDL_SetTextureBlendMode($texture, SDL::SDL_BLENDMODE_BLEND);
    $this->sdl->ffi->SDL_SetTextureScaleMode($texture, SDL::SDL_SCALE_MODE_NEAREST);
    return $texture;
  }

  protected function fontHandle(array $font): mixed {
    $size = max(1, (int)round((float)($font['size'] ?? $this->font->height)));
    $weight = strtolower((string)($font['weight'] ?? 'normal'));
    $style = strtolower((string)($font['style'] ?? 'normal'));
    $families = $font['families'] ?? [$font['family'] ?? 'NimbusMonoPS-Regular'];
    if (!is_array($families) || $families === []) {
      $families = [$family];
    }
    $families = array_values(array_unique(array_map('strval', $families)));
    $key = implode(',', $families) . "|{$size}|{$weight}|{$style}";
    if (isset($this->fonts[$key])) {
      return $this->fonts[$key];
    }
    $candidates = [];
    foreach ($families as $family) {
      if ($weight === 'bold' && $style === 'italic') {
        $candidates[] = $family . '-BoldItalic';
        $candidates[] = $family . '-BoldOblique';
      } else if ($weight === 'bold') {
        $candidates[] = $family . '-Bold';
      } else if ($style === 'italic') {
        $candidates[] = $family . '-Italic';
        $candidates[] = $family . '-Oblique';
      }
      $candidates[] = $family;
    }
    $candidates[] = 'NimbusMonoPS-Regular';
    $path = null;
    foreach ($candidates as $candidate) {
      try {
        $path = SdlFont::findPath($candidate, true);
        break;
      } catch (\Throwable) {
      }
    }
    if ($path === null) {
      $path = SdlFont::findPath('NimbusMonoPS-Regular');
    }
    $handle = $this->ttf->ffi->TTF_OpenFont($path, $size);
    if ($handle === null) {
      return $this->font->handle();
    }
    $this->ttf->ffi->TTF_SetFontHinting($handle, TTF::TTF_HINTING_LIGHT_SUBPIXEL);
    $this->fonts[$key] = $handle;
    return $handle;
  }

  protected function fontHeight(array $font): int {
    return max(1, (int)$this->ttf->ffi->TTF_GetFontHeight($this->fontHandle($font)));
  }

  protected function usesGridFont(array $font): bool {
    return $font === [];
  }

  protected function applyClip(?Rect $rect): void {
    if ($rect === null) {
      $this->clipRect->x = $this->surfaceX;
      $this->clipRect->y = $this->surfaceY;
      $this->clipRect->w = $this->windowWidth;
      $this->clipRect->h = $this->windowHeight;
      $this->sdl->ffi->SDL_SetRenderClipRect($this->renderer, $this->clipRectAddr);
      return;
    }
    $x = $this->offsetX + $rect->x * $this->font->letterWidth;
    $y = $this->offsetY + $rect->y * $this->font->rowHeight;
    $right = $x + $rect->width * $this->font->letterWidth;
    $bottom = $y + $rect->height * $this->font->rowHeight;
    $surfaceRight = $this->surfaceX + $this->windowWidth;
    $surfaceBottom = $this->surfaceY + $this->windowHeight;
    $x = max($this->surfaceX, $x);
    $y = max($this->surfaceY, $y);
    $right = min($surfaceRight, $right);
    $bottom = min($surfaceBottom, $bottom);
    $this->clipRect->x = $x;
    $this->clipRect->y = $y;
    $this->clipRect->w = max(0, $right - $x);
    $this->clipRect->h = max(0, $bottom - $y);
    $this->sdl->ffi->SDL_SetRenderClipRect($this->renderer, $this->clipRectAddr);
  }

  public function restoreWindowTarget(): void {
    $this->sdl->ffi->SDL_SetRenderTarget($this->renderer, null);
    $this->sdl->ffi->SDL_SetRenderViewport($this->renderer, null);
    $clip = $this->clips[count($this->clips) - 1] ?? null;
    $this->applyClip($clip);
  }

  public function destroy(): void {
    $this->atlas->destroy();
    foreach ($this->textures as $texture) {
      $texture->destroy();
    }
    $this->textures = [];
    foreach ($this->fonts as $handle) {
      if ($handle !== null) {
        $this->ttf->ffi->TTF_CloseFont($handle);
      }
    }
    $this->fonts = [];
  }

}
