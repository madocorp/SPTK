<?php

namespace SPTK\SDLWrapper;

/**
 * Loads SDL3_ttf through FFI for the SPTK SDL text renderer.
 */
class TTF {

  public const TTF_HINTING_NORMAL = 0;
  public const TTF_HINTING_LIGHT_SUBPIXEL = 4;

  public \FFI $ffi;

  public function __construct() {
    $this->ffi = \FFI::cdef(
      file_get_contents(APP_DIR . '/SPTK/SDLWrapper/sdl_ttf_extract.h'),
      APP_DIR . '/SPTK/SDLWrapper/libSDL3_ttf.so.0.2.3'
    );
  }

  public function close() {
    $this->ffi->SDL_Quit();
  }

}
