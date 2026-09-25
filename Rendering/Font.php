<?php

namespace SPTK\Rendering;

use SPTK\SDLWrapper\TTF;
use SPTK\Core\Color;

/** Owns one native font and exposes the cell metrics needed by the window. */
final class Font {

  private ?\FFI\CData $handle = null;
  private int $cellWidth = 1;
  private int $cellHeight = 1;

  public function __construct(private TTF $ttf) {
  }

  public function open(string $name, int $size): void {
    if ($this->handle !== null) {
      throw new \LogicException('Font is already open.');
    }
    $path = FontFinder::find($name);
    $this->handle = $this->ttf->ffi->TTF_OpenFont($path, (float) $size);
    if ($this->handle === null) {
      throw new \RuntimeException("Cannot open font: {$path}");
    }
    $advance = $this->ttf->ffi->new('int');
    if (!$this->ttf->ffi->TTF_GetGlyphMetrics($this->handle, ord('M'), null, null, null, null, \FFI::addr($advance))) {
      throw new \RuntimeException('Cannot measure the font cell width.');
    }
    $this->cellWidth = max(1, $advance->cdata);
    $this->cellHeight = max(1, $this->ttf->ffi->TTF_GetFontLineSkip($this->handle));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function surface(string $text, Color $fg): \FFI\CData {
    if ($this->handle === null) {
      throw new \LogicException('Font is not open.');
    }
    $color = $this->ttf->ffi->new('SDL_Color');
    $color->r = $fg->r;
    $color->g = $fg->g;
    $color->b = $fg->b;
    $color->a = 255;
    $surface = $this->ttf->ffi->TTF_RenderText_Blended($this->handle, $text, strlen($text), $color);
    if ($surface === null) {
      throw new \RuntimeException('Cannot rasterize text.');
    }
    return $surface;
  }

  public function releaseSurface(\FFI\CData $surface): void {
    $this->ttf->ffi->SDL_DestroySurface($surface);
  }

  public function close(): void {
    if ($this->handle !== null) {
      $this->ttf->ffi->TTF_CloseFont($this->handle);
      $this->handle = null;
    }
  }

}
