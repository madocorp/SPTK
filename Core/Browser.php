<?php

namespace SPTK\Core;

/** Opens URLs with the system default handler; tests can replace the opener. */
final class Browser {

  private static $opener = null;

  /** Open a web or mail link outside the application. */
  public static function open(string $url): void {
    if (self::$opener !== null) {
      (self::$opener)($url);
      return;
    }
    $sdl = \SPTK\App::sdl();
    if ($sdl === null) {
      throw new \RuntimeException('Cannot open ' . $url . ' without SDL.');
    }
    $sdl->checkReturnValue($sdl->ffi->SDL_OpenURL($url), 'SDL_OpenURL');
  }

  /** Replace the system opener, or restore it with null. */
  public static function setOpener(?callable $opener): void {
    self::$opener = $opener;
  }

}
