<?php

namespace SPTK\Rendering;

use SPTK\Core\Color;
use SPTK\Layout\Tile;
use SPTK\SDLWrapper\SDL;

/** Paints tile backgrounds and separator lines as pixel rectangles. */
final class PixelRenderer {

  private SDL $sdl;

  public function __construct(private \FFI\CData $ffiRenderer) {
    $this->sdl = \SPTK\App::sdl();
  }

  public function fill(Tile $area, Color $color): void {
    if ($area->width === 0 || $area->height === 0) {
      return;
    }
    $rect = $this->sdl->ffi->new('SDL_FRect');
    $rect->x = $area->x;
    $rect->y = $area->y;
    $rect->w = $area->width;
    $rect->h = $area->height;
    $this->check($this->sdl->ffi->SDL_SetRenderDrawColor($this->ffiRenderer, $color->r, $color->g, $color->b, 255));
    $this->check($this->sdl->ffi->SDL_RenderFillRect($this->ffiRenderer, \FFI::addr($rect)));
  }

  private function check(bool $success): void {
    if (!$success) {
      throw new \RuntimeException('SDL pixel rendering failed: ' . $this->sdl->error());
    }
  }

}
