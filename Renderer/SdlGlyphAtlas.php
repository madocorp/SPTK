<?php

namespace SPTK\Renderer;

use SPTK\Runtime\SdlFont;
use SPTK\SDLWrapper\SDL;
use SPTK\SDLWrapper\TTF;

/**
 * Caches rendered glyph alpha maps in an SDL texture atlas for one font.
 */
class SdlGlyphAtlas {

  protected const GLYPH_MAP_SIZE = 64;
  protected const MAP_PAD = 1;

  protected $texture;
  protected array $glyphs = [];
  protected int $nextGlyph = 0;
  protected $white;
  protected $rect;
  protected $rectAddr;

  public function __construct(
    protected SDL $sdl,
    protected TTF $ttf,
    protected mixed $renderer,
    protected SdlFont $font
  ) {
    $this->white = $this->ttf->ffi->new('SDL_Color');
    $this->white->r = 255;
    $this->white->g = 255;
    $this->white->b = 255;
    $this->white->a = 255;
    $this->rect = $this->sdl->ffi->new('SDL_Rect');
    $this->rectAddr = \FFI::addr($this->rect);
    $width = ($this->font->letterWidth + self::MAP_PAD * 2) * self::GLYPH_MAP_SIZE;
    $height = ($this->font->height + self::MAP_PAD * 2) * self::GLYPH_MAP_SIZE;
    $this->texture = $this->sdl->ffi->SDL_CreateTexture(
      $this->renderer,
      SDL::SDL_PIXELFORMAT_RGBA8888,
      SDL::SDL_TEXTUREACCESS_STATIC,
      $width,
      $height
    );
    if ($this->texture === null) {
      throw new \RuntimeException('Unable to create glyph atlas texture: ' . $this->sdl->error());
    }
    $zeroPixels = \FFI::new('uint8_t[' . ($width * $height * 4) . ']');
    $this->sdl->ffi->SDL_UpdateTexture($this->texture, null, $zeroPixels, $width * 4);
    $this->sdl->ffi->SDL_SetTextureBlendMode($this->texture, SDL::SDL_BLENDMODE_BLEND);
    $this->sdl->ffi->SDL_SetTextureScaleMode($this->texture, SDL::SDL_SCALE_MODE_NEAREST);
  }

  public function texture(): mixed {
    return $this->texture;
  }

  public function map(string $glyph): array {
    if (!isset($this->glyphs[$glyph])) {
      $this->cacheGlyph($glyph);
    }
    return $this->glyphs[$glyph];
  }

  protected function cacheGlyph(string $glyph): void {
    $index = $this->nextGlyph++;
    $cellWidth = $this->font->letterWidth;
    $cellHeight = $this->font->height;
    $slotY = (int)($index / self::GLYPH_MAP_SIZE);
    $slotX = $index % self::GLYPH_MAP_SIZE;
    $mapX = self::MAP_PAD + $slotX * ($cellWidth + self::MAP_PAD * 2);
    $mapY = self::MAP_PAD + $slotY * ($cellHeight + self::MAP_PAD * 2);
    $this->glyphs[$glyph] = [$mapX, $mapY];

    $surface = $this->ttf->ffi->TTF_RenderGlyph_Blended($this->font->handle(), mb_ord($glyph), $this->white);
    if ($surface === null) {
      return;
    }
    $surface2 = \FFI::cast($this->sdl->ffi->type('SDL_Surface*'), $surface);
    $srcSurface = $this->sdl->ffi->SDL_ConvertSurface($surface2, SDL::SDL_PIXELFORMAT_RGBA8888);
    if ($srcSurface === null) {
      $this->ttf->ffi->SDL_DestroySurface($surface);
      return;
    }
    $offsetX = 0;
    $offsetY = 0;
    if ($surface->w != $cellWidth || $surface->h != $cellHeight) {
      $metrics = $this->font->glyphMetrics($glyph);
      if ($surface->w != $cellWidth && $metrics[0] < 0) {
        $offsetX = -$metrics[0];
      }
      if ($surface->h != $cellHeight && $metrics[3] > $this->font->ascent) {
        $offsetY = $metrics[3] - $this->font->ascent;
      }
    }
    $this->rect->x = $mapX - $offsetX;
    $this->rect->y = $mapY - $offsetY;
    $this->rect->w = $surface->w;
    $this->rect->h = $surface->h;
    $this->sdl->ffi->SDL_UpdateTexture($this->texture, $this->rectAddr, $srcSurface->pixels, $srcSurface->pitch);
    $this->ttf->ffi->SDL_DestroySurface($surface);
    $this->sdl->ffi->SDL_DestroySurface($srcSurface);
  }

  public function destroy(): void {
    if ($this->texture !== null) {
      $this->sdl->ffi->SDL_DestroyTexture($this->texture);
      $this->texture = null;
    }
  }

  public function __destruct() {
    $this->destroy();
  }

}
