<?php

namespace SPTK\Core;

/** Shares clipboard text with SDL and keeps a headless in-memory value for tests. */
final class Clipboard {

  private static string $text = '';

  /** Store text locally and in the system clipboard when SDL is available. */
  public static function set(string $text): void {
    self::$text = $text;
    $sdl = \SPTK\App::sdl();
    if ($sdl !== null) {
      $sdl->checkReturnValue($sdl->ffi->SDL_SetClipboardText($text), 'SDL_SetClipboardText');
    }
  }

  /** Read the system clipboard or the local headless value. */
  public static function get(): string {
    $sdl = \SPTK\App::sdl();
    if ($sdl === null) {
      return self::$text;
    }
    $pointer = $sdl->ffi->SDL_GetClipboardText();
    if ($pointer === null || \FFI::isNull($pointer)) {
      return self::$text;
    }
    self::$text = \FFI::string($pointer);
    $sdl->ffi->SDL_free($pointer);
    return self::$text;
  }

}
