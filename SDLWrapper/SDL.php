<?php

namespace SPTK\SDLWrapper;

/**
 * Loads SDL3 through FFI and exposes constants used by the SPTK SDL backend.
 */
class SDL {

  public const SDL_INIT_VIDEO = 0x20;

  public const SDL_QUIT = 0x100;
  public const SDL_EVENT_WINDOW_EXPOSED = 0x204;
  public const SDL_EVENT_WINDOW_RESIZED = 0x206;
  public const SDL_EVENT_WINDOW_MAXIMIZED = 0x20a;
  public const SDL_EVENT_WINDOW_RESTORED = 0x20b;
  public const SDL_EVENT_WINDOW_CLOSE_REQUESTED = 0x210;

  public const SDL_EVENT_KEY_DOWN = 0x300;
  public const SDL_EVENT_KEY_UP = 0x301;
  public const SDL_EVENT_TEXT_INPUT = 0x303;

  public const SDL_PIXELFORMAT_RGBA8888 = 0x16462004;
  public const SDL_TEXTUREACCESS_STATIC = 0;
  public const SDL_TEXTUREACCESS_TARGET = 2;
  public const SDL_BLENDMODE_BLEND = 0x1;
  public const SDL_SCALE_MODE_NEAREST = 0;

  public const SDL_WINDOW_RESIZABLE = 0x20;
  public const SDL_WINDOW_HIDDEN = 0x8;
  public const SDL_WINDOW_MAXIMIZED = 0x80;
  public const SDL_WINDOW_FULLSCREEN = 0x01;

  public const KEY_RETURN = 13;
  public const KEY_ESCAPE = 27;
  public const KEY_BACKSPACE = 8;
  public const KEY_TAB = 9;
  public const KEY_SPACE = 32;
  public const KEY_DELETE = 127;
  public const KEY_SCANCODE_MASK = 1 << 30;
  public const KEY_INSERT = self::KEY_SCANCODE_MASK | 73;
  public const KEY_RIGHT = self::KEY_SCANCODE_MASK | 79;
  public const KEY_LEFT = self::KEY_SCANCODE_MASK | 80;
  public const KEY_DOWN = self::KEY_SCANCODE_MASK | 81;
  public const KEY_UP = self::KEY_SCANCODE_MASK | 82;
  public const KEY_HOME = self::KEY_SCANCODE_MASK | 74;
  public const KEY_PAGEUP = self::KEY_SCANCODE_MASK | 75;
  public const KEY_END = self::KEY_SCANCODE_MASK | 77;
  public const KEY_PAGEDOWN = self::KEY_SCANCODE_MASK | 78;
  public const KEY_F1 = self::KEY_SCANCODE_MASK | 58;
  public const KEY_F2 = self::KEY_SCANCODE_MASK | 59;
  public const KEY_F3 = self::KEY_SCANCODE_MASK | 60;
  public const KEY_F4 = self::KEY_SCANCODE_MASK | 61;
  public const KEY_F5 = self::KEY_SCANCODE_MASK | 62;
  public const KEY_F6 = self::KEY_SCANCODE_MASK | 63;
  public const KEY_F7 = self::KEY_SCANCODE_MASK | 64;
  public const KEY_F8 = self::KEY_SCANCODE_MASK | 65;
  public const KEY_F9 = self::KEY_SCANCODE_MASK | 66;
  public const KEY_F10 = self::KEY_SCANCODE_MASK | 67;
  public const KEY_F11 = self::KEY_SCANCODE_MASK | 68;
  public const KEY_F12 = self::KEY_SCANCODE_MASK | 69;
  public const KEY_KP_1 = self::KEY_SCANCODE_MASK | 89;
  public const KEY_KP_3 = self::KEY_SCANCODE_MASK | 91;
  public const KEY_KP_7 = self::KEY_SCANCODE_MASK | 95;
  public const KEY_KP_9 = self::KEY_SCANCODE_MASK | 97;

  public const MOD_SHIFT = 0x0003;
  public const MOD_CTRL = 0x00c0;
  public const MOD_ALT = 0x0300;

  public \FFI $ffi;

  public function __construct(?string $basePath = null) {
    $basePath ??= dirname(__DIR__) . '/SDLWrapper';
    $this->ffi = \FFI::cdef(
      file_get_contents($basePath . '/sdl_extract.h'),
      $basePath . '/libSDL3.so.0.2.21'
    );
  }

  public function error(): string {
    $error = $this->ffi->SDL_GetError();
    if ($error === null) {
      return '';
    }
    if (is_string($error)) {
      return $error;
    }
    return \FFI::string($error);
  }

}
