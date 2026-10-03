<?php

namespace SPTK\Core;

use SPTK\App;
use SPTK\SDLWrapper\SDL;

/** Reads and applies native window modes and sizes independently of tile rendering. */
final class WindowPlacement {

  /** Capture the current window mode and pixel dimensions for later restoration. */
  public static function capture(Window $window): array {
    $sdl = App::sdl();
    $native = self::native($window);
    $width = $sdl->ffi->new('int');
    $height = $sdl->ffi->new('int');
    $sdl->ffi->SDL_GetWindowSize($native, \FFI::addr($width), \FFI::addr($height));
    $flags = $sdl->ffi->SDL_GetWindowFlags($native);
    $mode = ($flags & SDL::SDL_WINDOW_FULLSCREEN) !== 0 ? 'fullscreen' : (($flags & SDL::SDL_WINDOW_MAXIMIZED) !== 0 ? 'maximized' : 'normal');
    return ['mode' => $mode, 'width' => $width->cdata, 'height' => $height->cdata];
  }

  /** Apply a native mode and optional pixel size, then remeasure and repaint the window. */
  public static function apply(Window $window, array $options): void {
    $mode = $options['mode'];
    if (!in_array($mode, ['normal', 'maximized', 'fullscreen'], true)) {
      throw new \InvalidArgumentException('Unknown window mode.');
    }
    if (isset($options['width'], $options['height']) && ($options['width'] < 1 || $options['height'] < 1)) {
      throw new \InvalidArgumentException('Window dimensions must be positive.');
    }
    $sdl = App::sdl();
    $native = self::native($window);
    $flags = $sdl->ffi->SDL_GetWindowFlags($native);
    if (($flags & SDL::SDL_WINDOW_FULLSCREEN) !== 0) {
      $sdl->checkReturnValue($sdl->ffi->SDL_SetWindowFullscreen($native, false), 'SDL_SetWindowFullscreen');
    }
    if (($flags & (SDL::SDL_WINDOW_MAXIMIZED | SDL::SDL_WINDOW_MINIMIZED)) !== 0) {
      $sdl->checkReturnValue($sdl->ffi->SDL_RestoreWindow($native), 'SDL_RestoreWindow');
    }
    $sdl->ffi->SDL_SyncWindow($native);
    if (isset($options['width'], $options['height'])) {
      $sdl->checkReturnValue($sdl->ffi->SDL_SetWindowSize($native, $options['width'], $options['height']), 'SDL_SetWindowSize');
    }
    if ($mode === 'fullscreen') {
      $sdl->checkReturnValue($sdl->ffi->SDL_SetWindowFullscreen($native, true), 'SDL_SetWindowFullscreen');
    } else if ($mode === 'maximized') {
      $sdl->checkReturnValue($sdl->ffi->SDL_MaximizeWindow($native), 'SDL_MaximizeWindow');
    }
    $sdl->ffi->SDL_SyncWindow($native);
    $window->resize();
  }

  /** Resolve a live native window through its public SDL identifier. */
  private static function native(Window $window): mixed {
    $native = App::sdl()->ffi->SDL_GetWindowFromID($window->id());
    if ($native === null) {
      throw new \RuntimeException('Window is closed.');
    }
    return $native;
  }

}
