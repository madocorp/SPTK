<?php

namespace SPTK\Core;

use SPTK\SDLWrapper\SDL;

/** Decodes an image and sets it as an SDL window's native icon. */
final class WindowIcon {

  /** Apply a PNG path relative to Layout, or an absolute path. */
  public static function apply(SDL $sdl, \FFI\CData $window, string $path): void {
    if (!str_starts_with($path, '/')) {
      $path = APP_DIR . '/Layout/' . $path;
    }
    $image = new RasterImage($path);
    $surface = $sdl->ffi->SDL_CreateSurface($image->width, $image->height, SDL::SDL_PIXELFORMAT_RGBA8888);
    if ($surface === null) {
      throw new \RuntimeException('Cannot create window icon surface: ' . $sdl->error());
    }
    try {
      $sdl->checkReturnValue($sdl->ffi->SDL_LockSurface($surface), 'SDL_LockSurface');
      try {
        $rowBytes = $image->width * 4;
        $pixels = \FFI::cast('char *', $surface->pixels);
        for ($row = 0; $row < $image->height; $row++) {
          \FFI::memcpy(\FFI::addr($pixels[$row * $surface->pitch]), substr($image->pixels, $row * $rowBytes, $rowBytes), $rowBytes);
        }
      } finally {
        $sdl->ffi->SDL_UnlockSurface($surface);
      }
      if (!$sdl->ffi->SDL_SetWindowIcon($window, $surface)) {
        $error = $sdl->error();
        if (!str_contains($error, 'not supported')) {
          throw new \RuntimeException('SDL_SetWindowIcon failed: ' . $error);
        }
      }
    } finally {
      $sdl->ffi->SDL_DestroySurface($surface);
    }
  }

}
