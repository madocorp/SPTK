<?php

namespace SPTK\Renderer;

use SPTK\Core\Color;
use SPTK\Core\Rect;
use SPTK\Core\Texture;
use SPTK\SDLWrapper\SDL;

/**
 * SDL-backed texture or bounded window drawing surface.
 */
class SdlTexture implements Texture {

  protected $rect;
  protected $rectAddr;
  protected $rect2;
  protected $rect2Addr;
  protected $clipRect;
  protected $clipRectAddr;

  public function __construct(
    protected SDL $sdl,
    protected mixed $renderer,
    protected ?\FFI\CData $texture,
    protected Rect $bounds,
    protected bool $ownsTexture,
    protected \Closure $restoreRenderer
  ) {
    $this->rect = $this->sdl->ffi->new('SDL_FRect');
    $this->rectAddr = \FFI::addr($this->rect);
    $this->rect2 = $this->sdl->ffi->new('SDL_FRect');
    $this->rect2Addr = \FFI::addr($this->rect2);
    $this->clipRect = $this->sdl->ffi->new('SDL_Rect');
    $this->clipRectAddr = \FFI::addr($this->clipRect);
  }

  public static function create(SDL $sdl, mixed $renderer, int $width, int $height, Color|string|int $background, \Closure $restoreRenderer): self {
    $width = max(1, $width);
    $height = max(1, $height);
    $texture = $sdl->ffi->SDL_CreateTexture(
      $renderer,
      SDL::SDL_PIXELFORMAT_RGBA8888,
      SDL::SDL_TEXTUREACCESS_TARGET,
      $width,
      $height
    );
    if ($texture === null) {
      throw new \RuntimeException('SDL_CreateTexture failed: ' . $sdl->error());
    }
    $sdl->ffi->SDL_SetTextureBlendMode($texture, SDL::SDL_BLENDMODE_BLEND);
    $sdl->ffi->SDL_SetTextureScaleMode($texture, SDL::SDL_SCALE_MODE_NEAREST);
    $instance = new self($sdl, $renderer, $texture, new Rect(0, 0, $width, $height), true, $restoreRenderer);
    $instance->clear($background);
    return $instance;
  }

  public static function view(SDL $sdl, mixed $renderer, Rect $bounds, \Closure $restoreRenderer): self {
    return new self($sdl, $renderer, null, $bounds, false, $restoreRenderer);
  }

  public function __destruct() {
    $this->destroy();
  }

  public function destroy(): void {
    if ($this->ownsTexture && $this->texture !== null) {
      $this->sdl->ffi->SDL_DestroyTexture($this->texture);
      $this->texture = null;
    }
  }

  public function width(): int {
    return $this->bounds->width;
  }

  public function height(): int {
    return $this->bounds->height;
  }

  public function clear(Color|string|int $color = 'transparent'): void {
    if ($this->texture !== null) {
      $color = Color::from($color);
      $this->activate();
      $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
      $this->sdl->ffi->SDL_RenderClear($this->renderer);
      $this->restore();
      return;
    }
    $this->fillRect(0, 0, $this->width(), $this->height(), $color);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $color = Color::from($color);
    $thickness = max(1, $thickness);
    if (abs($y1 - $y2) < 0.01) {
      $this->fillRect((int)round(min($x1, $x2)), (int)round($y1) - intdiv($thickness, 2), (int)round(abs($x2 - $x1)), $thickness, $color);
      return;
    }
    if (abs($x1 - $x2) < 0.01) {
      $this->fillRect((int)round($x1) - intdiv($thickness, 2), (int)round(min($y1, $y2)), $thickness, (int)round(abs($y2 - $y1)), $color);
      return;
    }
    $this->activate();
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $this->sdl->ffi->SDL_RenderLine(
      $this->renderer,
      (float)($this->bounds->x + $x1),
      (float)($this->bounds->y + $y1),
      (float)($this->bounds->x + $x2),
      (float)($this->bounds->y + $y2)
    );
    $this->restore();
  }

  public function drawRect(int $x, int $y, int $width, int $height, Color|string|int $color, int $thickness = 1): void {
    if ($width <= 0 || $height <= 0) {
      return;
    }
    $thickness = max(1, $thickness);
    $this->fillRect($x, $y, $width, $thickness, $color);
    $this->fillRect($x, $y + $height - $thickness, $width, $thickness, $color);
    $this->fillRect($x, $y, $thickness, $height, $color);
    $this->fillRect($x + $width - $thickness, $y, $thickness, $height, $color);
  }

  public function fillRect(int $x, int $y, int $width, int $height, Color|string|int $color): void {
    if ($width <= 0 || $height <= 0) {
      return;
    }
    $rect = $this->localRect($x, $y, $width, $height);
    if ($rect === null) {
      return;
    }
    $color = Color::from($color);
    $this->activate();
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->renderer, $color->r, $color->g, $color->b, $color->a);
    $this->rect->x = (float)($this->coordinateOffsetX() + $rect->x);
    $this->rect->y = (float)($this->coordinateOffsetY() + $rect->y);
    $this->rect->w = (float)$rect->width;
    $this->rect->h = (float)$rect->height;
    $this->sdl->ffi->SDL_RenderFillRect($this->renderer, $this->rectAddr);
    $this->restore();
  }

  public function copyTo(Texture $target, int $x, int $y): void {
    $this->copy($target, 0, 0, $x, $y, $this->width(), $this->height());
  }

  public function copy(Texture $target, int $sourceX, int $sourceY, int $targetX, int $targetY, int $width, int $height): void {
    if (!$target instanceof self) {
      throw new \InvalidArgumentException('SdlTexture can only copy to another SdlTexture.');
    }
    if ($this->texture === null || $width <= 0 || $height <= 0) {
      return;
    }
    [$sourceX, $sourceY, $targetX, $targetY, $width, $height] = $this->clippedCopyRect($target, $sourceX, $sourceY, $targetX, $targetY, $width, $height);
    if ($width <= 0 || $height <= 0) {
      return;
    }
    $target->activate();
    $this->rect->x = (float)($this->coordinateOffsetX() + $sourceX);
    $this->rect->y = (float)($this->coordinateOffsetY() + $sourceY);
    $this->rect->w = (float)$width;
    $this->rect->h = (float)$height;
    $this->rect2->x = (float)($target->coordinateOffsetX() + $targetX);
    $this->rect2->y = (float)($target->coordinateOffsetY() + $targetY);
    $this->rect2->w = (float)$width;
    $this->rect2->h = (float)$height;
    $this->sdl->ffi->SDL_RenderTexture($this->renderer, $this->texture, $this->rectAddr, $this->rect2Addr);
    $this->restore();
  }

  protected function localRect(int $x, int $y, int $width, int $height): ?Rect {
    $x1 = max(0, $x);
    $y1 = max(0, $y);
    $x2 = min($this->width(), $x + $width);
    $y2 = min($this->height(), $y + $height);
    if ($x2 <= $x1 || $y2 <= $y1) {
      return null;
    }
    return new Rect($x1, $y1, $x2 - $x1, $y2 - $y1);
  }

  protected function clippedCopyRect(self $target, int $sourceX, int $sourceY, int $targetX, int $targetY, int $width, int $height): array {
    if ($sourceX < 0) {
      $targetX -= $sourceX;
      $width += $sourceX;
      $sourceX = 0;
    }
    if ($sourceY < 0) {
      $targetY -= $sourceY;
      $height += $sourceY;
      $sourceY = 0;
    }
    if ($targetX < 0) {
      $sourceX -= $targetX;
      $width += $targetX;
      $targetX = 0;
    }
    if ($targetY < 0) {
      $sourceY -= $targetY;
      $height += $targetY;
      $targetY = 0;
    }
    $width = min($width, $this->width() - $sourceX, $target->width() - $targetX);
    $height = min($height, $this->height() - $sourceY, $target->height() - $targetY);
    return [$sourceX, $sourceY, $targetX, $targetY, $width, $height];
  }

  protected function activate(): void {
    $this->sdl->ffi->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->ffi->SDL_SetRenderViewport($this->renderer, null);
    $this->clipRect->x = $this->coordinateOffsetX();
    $this->clipRect->y = $this->coordinateOffsetY();
    $this->clipRect->w = $this->bounds->width;
    $this->clipRect->h = $this->bounds->height;
    $this->sdl->ffi->SDL_SetRenderClipRect($this->renderer, $this->clipRectAddr);
  }

  protected function coordinateOffsetX(): int {
    return $this->texture === null ? $this->bounds->x : 0;
  }

  protected function coordinateOffsetY(): int {
    return $this->texture === null ? $this->bounds->y : 0;
  }

  protected function restore(): void {
    ($this->restoreRenderer)();
  }

}
