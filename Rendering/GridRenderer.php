<?php

namespace SPTK\Rendering;

use SPTK\Core\Cell;
use SPTK\SDLWrapper\SDL;

/** Paints cell backgrounds, then leading glyphs clipped to their allocated columns. */
final class GridRenderer {

  private GlyphAtlas $atlas;
  private int $ox = 0;
  private int $oy = 0;
  private SDL $sdl;
  private Font $font;

  public function __construct(\FFI\CData $ffiRenderer) {
    $this->sdl = \SPTK\App::sdl();
    $this->font = \SPTK\App::font();
    $this->atlas = new GlyphAtlas($this->sdl, $ffiRenderer, $this->font);
  }

  public function setOffset(int $ox, int $oy) {
    $this->ox = $ox;
    $this->oy = $oy;
  }

  public function draw(\FFI\CData $ffiRenderer, Grid $grid): void {
    $ffi = $this->sdl->ffi;
    $this->check($ffi->SDL_SetRenderDrawColor($ffiRenderer, 24, 28, 36, 255));
    $this->check($ffi->SDL_RenderClear($ffiRenderer));
    try {
      $this->drawBackgrounds($ffiRenderer, $grid);
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
    $this->check($ffi->SDL_RenderPresent($ffiRenderer));
  }

  private function drawBackgrounds(\FFI\CData $ffiRenderer, Grid $grid): void {
    $ffi = $this->sdl->ffi;
    $rect = $ffi->new('SDL_FRect');
    $rect->h = $this->font->cellHeight();
    for ($y = 0; $y < $grid->height(); $y++) {
      $rect->y = $y * $rect->h + $this->oy;;
      for ($x = 0; $x < $grid->width(); $x = $end) {
        $color = $grid->cell($x, $y)->bg;
        $end = $x + 1;
        while ($end < $grid->width() && $grid->cell($end, $y)->bg == $color) {
          $end++;
        }
        $rect->x = $x * $this->font->cellWidth() + $this->ox;
        $rect->w = ($end - $x) * $this->font->cellWidth();
        $this->check($ffi->SDL_SetRenderDrawColor($ffiRenderer, $color->r, $color->g, $color->b, 255));
        $this->check($ffi->SDL_RenderFillRect($ffiRenderer, \FFI::addr($rect)));
      }
    }
  }

  private function drawGlyph(\FFI\CData $ffiRenderer, Cell $cell, int $x, int $y): void {
    $ffi = $this->sdl->ffi;
    $source = $this->atlas->map($cell->glyph, $cell->width);
    $texture = $this->atlas->texture();
    $this->check($ffi->SDL_SetTextureColorMod($texture, $cell->fg->r, $cell->fg->g, $cell->fg->b));
    $destination = $ffi->new('SDL_FRect');
    $destination->x = $x * $this->font->cellWidth() + $this->ox;
    $destination->y = $y * $this->font->cellHeight() + $this->oy;
    $destination->w = $source->w;
    $destination->h = $source->h;
    $this->check($ffi->SDL_RenderTexture($ffiRenderer, $texture, \FFI::addr($source), \FFI::addr($destination)));
  }

  /** Release the atlas before its native renderer dies, or after a device reset. */
  public function close(): void {
    $this->atlas->close();
  }

  private function check(bool $success): void {
    if (!$success) {
      throw new \RuntimeException('SDL rendering failed: ' . $this->sdl->error());
    }
  }

}
