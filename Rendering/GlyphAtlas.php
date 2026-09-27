<?php

namespace SPTK\Rendering;

use SPTK\Core\Color;
use SPTK\Rendering\Glyph\GeometryGlyph;
use SPTK\SDLWrapper\SDL;

/** Caches white glyph bitmaps in one texture owned by one native renderer/font. */
final class GlyphAtlas {

  private ?\FFI\CData $texture = null;
  private array $glyphs = [];
  private int $next = 0;
  private int $slotWidth;
  private int $slotHeight;
  private Color $white;
  private \FFI\CData $sourceRect;
  private \FFI\CData $blitPosition;
  private \FFI\CData $updateRect;

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
    $this->white = new Color(255, 255, 255);
    $this->sourceRect = $sdl->ffi->new('SDL_FRect');
    $this->blitPosition = $sdl->ffi->new('SDL_Rect');
    $this->blitPosition->x = $this->blitPosition->y = 1;
    $this->updateRect = $sdl->ffi->new('SDL_Rect');
    $this->updateRect->w = $this->slotWidth;
    $this->updateRect->h = $this->slotHeight;
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

  /** Return a borrowed source rectangle valid until the next map call. */
  public function map(string $glyph, int $columns): \FFI\CData {
    $this->texture();
    if (!in_array($columns, [1, 2], true)) {
      throw new \InvalidArgumentException('Atlas glyphs occupy one or two columns.');
    }
    $key = $columns . ':' . $glyph;
    $slot = $this->glyphs[$key] ?? null;
    if ($slot === null) {
      if ($this->next === $this->side * $this->side) {
        // Updating a texture flushes SDL's queued users before reusing its pixels.
        $this->glyphs = [];
        $this->next = 0;
      }
      $slot = $this->next;
      $this->upload($glyph, $columns, ($slot % $this->side) * $this->slotWidth, intdiv($slot, $this->side) * $this->slotHeight);
      $this->glyphs[$key] = $slot;
      $this->next++;
    }
    $this->sourceRect->x = ($slot % $this->side) * $this->slotWidth + 1;
    $this->sourceRect->y = intdiv($slot, $this->side) * $this->slotHeight + 1;
    $this->sourceRect->w = $columns * $this->font->cellWidth();
    $this->sourceRect->h = $this->font->cellHeight();
    return $this->sourceRect;
  }

  public function close(): void {
    if ($this->texture !== null) {
      $this->sdl->ffi->SDL_DestroyTexture($this->texture);
      $this->texture = null;
    }
    $this->glyphs = [];
  }

  /** Upload one font or geometry glyph into an atlas slot. */
  private function upload(string $glyph, int $columns, int $x, int $y): void {
    $ffi = $this->sdl->ffi;
    $geometry = GeometryGlyph::supports($glyph);
    $surface = $geometry
      ? $this->geometrySurface($glyph, $columns * $this->font->cellWidth(), $this->font->cellHeight())
      : $this->font->surface($glyph, $this->white);
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
        $ret = $ffi->SDL_BlitSurface($source, null, $slot, \FFI::addr($this->blitPosition));
        $this->sdl->checkReturnValue($ret, 'SDL_BlitSurface');
        $this->updateRect->x = $x;
        $this->updateRect->y = $y;
        $ret = $ffi->SDL_UpdateTexture($this->texture(), \FFI::addr($this->updateRect), $slot->pixels, $slot->pitch);
        $this->sdl->checkReturnValue($ret, 'SDL_UpdateTexture');
      } finally {
        $ffi->SDL_DestroySurface($slot);
      }
    } finally {
      if ($geometry) {
        $ffi->SDL_DestroySurface($surface);
      } else {
        $this->font->releaseSurface($surface);
      }
    }
  }

  /** Rasterize a geometry mask once as a white, transparent SDL surface. */
  private function geometrySurface(string $glyph, int $width, int $height): \FFI\CData {
    $ffi = $this->sdl->ffi;
    $surface = $ffi->SDL_CreateSurface($width, $height, SDL::SDL_PIXELFORMAT_RGBA8888);
    if ($surface === null) {
      throw new \RuntimeException('Cannot create geometry glyph surface: ' . $this->sdl->error());
    }
    try {
      $this->sdl->checkReturnValue($ffi->SDL_ClearSurface($surface, 0, 0, 0, 0), 'SDL_ClearSurface');
      $this->sdl->checkReturnValue($ffi->SDL_LockSurface($surface), 'SDL_LockSurface');
      try {
        $pixels = \FFI::cast('uint32_t *', $surface->pixels);
        $stride = intdiv($surface->pitch, 4);
        foreach (GeometryGlyph::spans($glyph, $width, $height) as $y => $row) {
          foreach ($row as [$x, $length]) {
            for ($column = $x; $column < $x + $length; $column++) {
              $pixels[$y * $stride + $column] = 0xffffffff;
            }
          }
        }
      } finally {
        $ffi->SDL_UnlockSurface($surface);
      }
      return $surface;
    } catch (\Throwable $error) {
      $ffi->SDL_DestroySurface($surface);
      throw $error;
    }
  }

}
