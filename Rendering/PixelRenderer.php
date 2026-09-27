<?php

namespace SPTK\Rendering;

use SPTK\Core\{Color, RasterImage};
use SPTK\Layout\Tile;
use SPTK\SDLWrapper\SDL;

/** Paints tile backgrounds and separator lines as pixel rectangles. */
final class PixelRenderer {

  private SDL $sdl;
  private \FFI\CData $rect;
  private \FFI\CData $clipRect;
  private ?int $drawColor = null;
  private array $images = [];
  private array $usedImages = [];

  public function __construct(private \FFI\CData $ffiRenderer) {
    $this->sdl = \SPTK\App::sdl();
    $this->rect = $this->sdl->ffi->new('SDL_FRect');
    $this->clipRect = $this->sdl->ffi->new('SDL_Rect');
  }

  public function fill(Tile $area, Color $color): void {
    if ($area->width === 0 || $area->height === 0) {
      return;
    }
    $this->rect->x = $area->x;
    $this->rect->y = $area->y;
    $this->rect->w = $area->width;
    $this->rect->h = $area->height;
    $this->setDrawColor($color);
    $ret = $this->sdl->ffi->SDL_RenderFillRect($this->ffiRenderer, \FFI::addr($this->rect));
    $this->sdl->checkReturnValue($ret, 'SDL_RenderFillRect');
  }

  /** Set the renderer draw color only when its RGB value changes. */
  public function setDrawColor(Color $color): void {
    $rgb = ($color->r << 16) | ($color->g << 8) | $color->b;
    if ($this->drawColor === $rgb) {
      return;
    }
    $ret = $this->sdl->ffi->SDL_SetRenderDrawColor($this->ffiRenderer, $color->r, $color->g, $color->b, 255);
    $this->sdl->checkReturnValue($ret, 'SDL_SetRenderDrawColor');
    $this->drawColor = $rgb;
  }

  /** Forget the cached color after an external renderer state change. */
  public function invalidateDrawColor(): void {
    $this->drawColor = null;
  }

  /** Start tracking image textures used by the next frame. */
  public function beginImages(): void {
    $this->usedImages = [];
  }

  /** Draw a decoded image while clipping all pixels to its widget tile. */
  public function image(RasterImage $image, Tile $area, Tile $clip, bool $selected): void {
    if ($area->width < 1 || $area->height < 1 || $clip->width < 1 || $clip->height < 1) {
      return;
    }
    if ($area->x >= $clip->x + $clip->width || $area->y >= $clip->y + $clip->height || $area->x + $area->width <= $clip->x || $area->y + $area->height <= $clip->y) {
      return;
    }
    $id = spl_object_id($image);
    $this->usedImages[$id] = true;
    $texture = $this->images[$id]['texture'] ?? null;
    if ($texture === null) {
      $texture = $this->upload($image);
      $this->images[$id] = ['image' => $image, 'texture' => $texture];
    }
    $shade = $selected ? 255 : (int)round(255 * 0.45);
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetTextureColorMod($texture, $shade, $shade, $shade), 'SDL_SetTextureColorMod');
    $this->rect->x = $area->x;
    $this->rect->y = $area->y;
    $this->rect->w = $area->width;
    $this->rect->h = $area->height;
    $this->clipRect->x = $clip->x;
    $this->clipRect->y = $clip->y;
    $this->clipRect->w = $clip->width;
    $this->clipRect->h = $clip->height;
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderClipRect($this->ffiRenderer, \FFI::addr($this->clipRect)), 'SDL_SetRenderClipRect');
    try {
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_RenderTexture($this->ffiRenderer, $texture, null, \FFI::addr($this->rect)), 'SDL_RenderTexture');
    } finally {
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderClipRect($this->ffiRenderer, null), 'SDL_SetRenderClipRect');
    }
  }

  /** Release image textures absent from the completed frame. */
  public function endImages(): void {
    foreach ($this->images as $id => $entry) {
      if (!isset($this->usedImages[$id])) {
        $this->sdl->ffi->SDL_DestroyTexture($entry['texture']);
        unset($this->images[$id]);
      }
    }
  }

  /** Release cached native textures before their renderer closes. */
  public function close(): void {
    foreach ($this->images as $entry) {
      $this->sdl->ffi->SDL_DestroyTexture($entry['texture']);
    }
    $this->images = [];
  }

  /** Upload one immutable raster to this window's SDL renderer. */
  private function upload(RasterImage $image): \FFI\CData {
    $ffi = $this->sdl->ffi;
    $texture = $ffi->SDL_CreateTexture($this->ffiRenderer, SDL::SDL_PIXELFORMAT_RGBA8888, SDL::SDL_TEXTUREACCESS_STATIC, $image->width, $image->height);
    if ($texture === null) {
      throw new \RuntimeException('SDL_CreateTexture failed: ' . $this->sdl->error());
    }
    try {
      $bytes = $image->pixels;
      $pixels = \FFI::new('char[' . strlen($bytes) . ']');
      \FFI::memcpy($pixels, $bytes, strlen($bytes));
      $this->sdl->checkReturnValue($ffi->SDL_UpdateTexture($texture, null, $pixels, $image->width * 4), 'SDL_UpdateTexture');
      $this->sdl->checkReturnValue($ffi->SDL_SetTextureBlendMode($texture, SDL::SDL_BLENDMODE_BLEND), 'SDL_SetTextureBlendMode');
      $this->sdl->checkReturnValue($ffi->SDL_SetTextureScaleMode($texture, SDL::SDL_SCALE_MODE_LINEAR), 'SDL_SetTextureScaleMode');
      return $texture;
    } catch (\Throwable $error) {
      $ffi->SDL_DestroyTexture($texture);
      throw $error;
    }
  }

}
