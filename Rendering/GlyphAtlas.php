<?php

namespace SPTK\Rendering;

use SPTK\Core\Color;
use SPTK\SDLWrapper\SDL;

/** Caches white glyph bitmaps in one texture owned by one native renderer/font. */
final class GlyphAtlas {

  private ?\FFI\CData $texture = null;
  private array $glyphs = [];
  private int $next = 0;
  private int $slotWidth;
  private int $slotHeight;

  public function __construct(
    private SDL $sdl,
    private \FFI\CData $ffiRenderer,
    private Font $font,
    private int $side = 32,
  ) {
    if ($side < 1 || $side > 64) {
      throw new \InvalidArgumentException('Atlas side must be between 1 and 64 slots.');
    }
    $this->slotWidth = 2 * $font->cellWidth() + 2;
    $this->slotHeight = $font->cellHeight() + 2;
    $this->texture = $sdl->ffi->SDL_CreateTexture(
      $ffiRenderer, SDL::SDL_PIXELFORMAT_RGBA8888, SDL::SDL_TEXTUREACCESS_STATIC,
      $this->slotWidth * $side, $this->slotHeight * $side,
    );
    if ($this->texture === null) {
      throw new \RuntimeException('Cannot create glyph atlas: ' . $sdl->error());
    }
    try {
      $ret = (bool) $sdl->ffi->SDL_SetTextureBlendMode($this->texture, SDL::SDL_BLENDMODE_BLEND);
      $sdl->checkReturnValue($ret, 'SDL_SetTextureBlendMode');
      $ret = $sdl->ffi->SDL_SetTextureScaleMode($this->texture, SDL::SDL_SCALE_MODE_NEAREST);
      $sdl->checkReturnValue($ret, 'SDL_SetTextureScaleMode');
    } catch (\Throwable $error) {
      $this->close();
      throw $error;
    }
  }

  public function texture(): \FFI\CData {
    if ($this->texture === null) {
      throw new \LogicException('Glyph atlas is closed.');
    }
    return $this->texture;
  }

  /** Returned source rectangle is borrowed; callers must not modify it. */
  public function map(string $glyph, int $columns): \FFI\CData {
    $this->texture();
    if (!in_array($columns, [1, 2], true)) {
      throw new \InvalidArgumentException('Atlas glyphs occupy one or two columns.');
    }
    $key = $columns . ':' . $glyph;
    if (isset($this->glyphs[$key])) {
      return $this->glyphs[$key];
    }
    if ($this->next === $this->side * $this->side) {
      // Updating a texture flushes SDL's queued users before reusing its pixels.
      $this->glyphs = [];
      $this->next = 0;
    }
    $x = ($this->next % $this->side) * $this->slotWidth;
    $y = intdiv($this->next, $this->side) * $this->slotHeight;
    $this->upload($glyph, $x, $y);
    $source = $this->sdl->ffi->new('SDL_FRect');
    $source->x = $x + 1;
    $source->y = $y + 1;
    $source->w = $columns * $this->font->cellWidth();
    $source->h = $this->font->cellHeight();
    $this->next++;
    return $this->glyphs[$key] = $source;
  }

  public function close(): void {
    if ($this->texture !== null) {
      $this->sdl->ffi->SDL_DestroyTexture($this->texture);
      $this->texture = null;
    }
    $this->glyphs = [];
  }

  private function upload(string $glyph, int $x, int $y): void {
    $ffi = $this->sdl->ffi;
    $surface = $this->font->surface($glyph, new Color(255, 255, 255));
    try {
      $slot = $ffi->SDL_CreateSurface($this->slotWidth, $this->slotHeight, SDL::SDL_PIXELFORMAT_RGBA8888);
      if ($slot === null) {
        throw new \RuntimeException('Cannot allocate atlas slot: ' . $this->sdl->error());
      }
      try {
        $ret = $ffi->SDL_ClearSurface($slot, 0, 0, 0, 0);
        $this->sdl->checkReturnValue($ret, 'SDL_ClearSurface');
        $source = $ffi->cast('SDL_Surface *', $surface);
        $ret = $ffi->SDL_SetSurfaceBlendMode($source, 0);
        $this->sdl->checkReturnValue($ret, 'SDL_SetSurfaceBlendMode');
        $position = $ffi->new('SDL_Rect');
        $position->x = $position->y = 1;
        $ret = $ffi->SDL_BlitSurface($source, null, $slot, \FFI::addr($position));
        $this->sdl->checkReturnValue($ret, 'SDL_BlitSurface');
        $rect = $ffi->new('SDL_Rect');
        $rect->x = $x;
        $rect->y = $y;
        $rect->w = $this->slotWidth;
        $rect->h = $this->slotHeight;
        $ret = $ffi->SDL_UpdateTexture($this->texture(), \FFI::addr($rect), $slot->pixels, $slot->pitch);
        $this->sdl->checkReturnValue($ret, 'SDL_UpdateTexture');
      } finally {
        $ffi->SDL_DestroySurface($slot);
      }
    } finally {
      $this->font->releaseSurface($surface);
    }
  }

}
