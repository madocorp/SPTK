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
  private \FFI\CData $glyphRect;
  private ?int $glyphColor = null;

  public function __construct(\FFI\CData $ffiRenderer, PixelRenderer $pixels) {
    $this->sdl = \SPTK\App::sdl();
    $this->font = \SPTK\App::font();
    $this->pixels = $pixels;
    $this->atlas = new GlyphAtlas($this->sdl, $ffiRenderer, $this->font);
    $this->glyphRect = $this->sdl->ffi->new('SDL_FRect');
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
  }

  /** Redraw cells written by the current widget update. */
  public function drawDirty(\FFI\CData $ffiRenderer, Grid $grid): int {
    $dirty = $grid->dirtyCells();
    try {
      foreach ($dirty as [$x, $y, $cell]) {
        $this->pixels->fill(new Tile(
          $x * $this->font->cellWidth() + $this->ox,
          $y * $this->font->cellHeight() + $this->oy,
          $this->font->cellWidth() * $cell->width,
          $this->font->cellHeight(),
        ), $cell->bg);
      }
      foreach ($dirty as [$x, $y, $cell]) {
        if ($cell->glyph !== ' ') {
          $this->drawGlyph($ffiRenderer, $cell, $x, $y);
        }
      }
    } finally {
      $this->sdl->ffi->SDL_SetRenderClipRect($ffiRenderer, null);
    }
    return count($dirty);
  }

  /** Redraw every cell of a pixel widget tile after its background is cleared. */
  public function drawTile(\FFI\CData $ffiRenderer, Grid $grid, Tile $tile): void {
    $right = min($grid->width(), $tile->x + $tile->width);
    $bottom = min($grid->height(), $tile->y + $tile->height);
    try {
      for ($y = max(0, $tile->y); $y < $bottom; $y++) {
        for ($x = max(0, $tile->x); $x < $right; $x++) {
          $cell = $grid->cell($x, $y);
          if ($cell->width !== 0) {
            $this->pixels->fill(new Tile($x * $this->font->cellWidth() + $this->ox, $y * $this->font->cellHeight() + $this->oy, $this->font->cellWidth() * $cell->width, $this->font->cellHeight()), $cell->bg);
          }
        }
      }
      for ($y = max(0, $tile->y); $y < $bottom; $y++) {
        for ($x = max(0, $tile->x); $x < $right; $x++) {
          $cell = $grid->cell($x, $y);
          if ($cell->width !== 0 && $cell->glyph !== ' ') {
            $this->drawGlyph($ffiRenderer, $cell, $x, $y);
          }
        }
      }
    } finally {
      $this->sdl->ffi->SDL_SetRenderClipRect($ffiRenderer, null);
    }
  }

  private function drawGlyph(\FFI\CData $ffiRenderer, Cell $cell, int $x, int $y): void {
    $area = new Tile(
      $x * $this->font->cellWidth() + $this->ox,
      $y * $this->font->cellHeight() + $this->oy,
      $this->font->cellWidth() * $cell->width,
      $this->font->cellHeight(),
    );
    $ffi = $this->sdl->ffi;
    $source = $this->atlas->map($cell->glyph, $cell->width);
    $texture = $this->atlas->texture();
    $rgb = ($cell->fg->r << 16) | ($cell->fg->g << 8) | $cell->fg->b;
    if ($this->glyphColor !== $rgb) {
      $ret = $ffi->SDL_SetTextureColorMod($texture, $cell->fg->r, $cell->fg->g, $cell->fg->b);
      $this->sdl->checkReturnValue($ret, 'SDL_SetTextureColorMod');
      $this->glyphColor = $rgb;
    }
    $this->glyphRect->x = $area->x;
    $this->glyphRect->y = $area->y;
    $this->glyphRect->w = $source->w;
    $this->glyphRect->h = $source->h;
    $ret = $ffi->SDL_RenderTexture($ffiRenderer, $texture, \FFI::addr($source), \FFI::addr($this->glyphRect));
    $this->sdl->checkReturnValue($ret, 'SDL_RenderTexture');
  }

  /** Release the atlas before its native renderer dies, or after a device reset. */
  public function close(): void {
    $this->atlas->close();
  }

}
