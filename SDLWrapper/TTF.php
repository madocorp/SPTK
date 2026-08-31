<?php

namespace SPTK\SDLWrapper;

/**
 * Loads SDL3_ttf through FFI for the SPTK SDL text renderer.
 */
class TTF {

  public const TTF_HINTING_NORMAL = 0;
  public const TTF_HINTING_LIGHT_SUBPIXEL = 4;

  public \FFI $ffi;

  public function __construct(?string $basePath = null) {
    $basePath ??= dirname(__DIR__) . '/SDLWrapper';
    $this->ffi = \FFI::cdef(
      file_get_contents($basePath . '/sdl_ttf_extract.h'),
      $basePath . '/libSDL3_ttf.so.0.2.3'
    );
  }

}
