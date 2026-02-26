<?php

namespace SPTK\SDLWrapper;

class TTF {

  const TTF_HINTING_NORMAL = 0;
  const TTF_HINTING_LIGHT = 1;
  const TTF_HINTING_MONO = 2;
  const TTF_HINTING_NONE = 3;
  const TTF_HINTING_LIGHT_SUBPIXEL = 4;

  public static $instance;

  public $ttf;

  public function __construct() {
    if (!is_null(self::$instance)) {
      throw new \Exception("SPTK\\SDL is a singleton, you can't instantiate more than once");
    }
    self::$instance = $this;
    $dir = \SPTK\App::$instance->getDir();
    $this->ttf = \FFI::cdef(file_get_contents($x = "{$dir}/SPTK/SDLWrapper/sdl_ttf_extract.h"), "{$dir}/SPTK/SDLWrapper/libSDL3_ttf.so");
    $this->ttf->TTF_Init();
  }

  public function __destruct() {
    $this->ttf->TTF_Quit();
  }

}