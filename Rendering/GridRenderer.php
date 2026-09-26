<?php

namespace SPTK\Rendering;

use SPTK\Core\Cell;
use SPTK\Layout\Tile;
use SPTK\SDLWrapper\SDL;

/** Paints leading glyphs clipped to their allocated columns. */
final class GridRenderer {

  private GlyphAtlas $atlas;
  private int $ox = 0;
  private int $oy = 0;
  private SDL $sdl;
  private Font $font;
  private PixelRenderer $pixels;

  public function __construct(\FFI\CData $ffiRenderer) {
    $this->sdl = \SPTK\App::sdl();
    $this->font = \SPTK\App::font();
    $this->pixels = new PixelRenderer($ffiRenderer);
    $this->atlas = new GlyphAtlas($this->sdl, $ffiRenderer, $this->font);
  }

  public function setOffset(int $ox, int $oy) {
    $this->ox = $ox;
    $this->oy = $oy;
  }

  public function draw(\FFI\CData $ffiRenderer, Grid $grid): void {
    $ffi = $this->sdl->ffi;
    try {
      for ($y = 0; $y < $grid->height(); $y++) {
        for ($x = 0; $x < $grid->width(); $x++) {
          $cell = $grid->cell($x, $y);
          if ($cell->width === 0 || ($cell->bg->r === 0 && $cell->bg->g === 0 && $cell->bg->b === 0)) {
            continue;
          }
          $area = new Tile(
            $x * $this->font->cellWidth() + $this->ox,
            $y * $this->font->cellHeight() + $this->oy,
            $this->font->cellWidth() * $cell->width,
            $this->font->cellHeight(),
          );
          $this->pixels->fill($area, $cell->bg);
        }
      }
      for ($y = 0; $y < $grid->height(); $y++) {
        for ($x = 0; $x < $grid->width(); $x++) {
          $cell = $grid->cell($x, $y);
          if ($cell->width !== 0 && $cell->glyph !== ' ') {
            $this->drawGlyph($ffiRenderer, $cell, $x, $y);
          }
        }
      }
    } finally {
      $ffi->SDL_SetRenderClipRect($ffiRenderer, null);
    }
    $ret = $ffi->SDL_RenderPresent($ffiRenderer);
    $this->sdl->checkReturnValue($ret, 'SDL_RenderPresent');
  }

  private function drawGlyph(\FFI\CData $ffiRenderer, Cell $cell, int $x, int $y): void {
    $ffi = $this->sdl->ffi;
    $source = $this->atlas->map($cell->glyph, $cell->width);
    $texture = $this->atlas->texture();
    $ret = $ffi->SDL_SetTextureColorMod($texture, $cell->fg->r, $cell->fg->g, $cell->fg->b);
    $this->sdl->checkReturnValue($ret, 'SDL_SetTextureColorMod');
    $destination = $ffi->new('SDL_FRect');
    $destination->x = $x * $this->font->cellWidth() + $this->ox;
    $destination->y = $y * $this->font->cellHeight() + $this->oy;
    $destination->w = $source->w;
    $destination->h = $source->h;
    $ret = $ffi->SDL_RenderTexture($ffiRenderer, $texture, \FFI::addr($source), \FFI::addr($destination));
    $this->sdl->checkReturnValue($ret, 'SDL_RenderTexture');
  }

  /** Release the atlas before its native renderer dies, or after a device reset. */
  public function close(): void {
    $this->atlas->close();
  }

}
