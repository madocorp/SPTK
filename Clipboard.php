<?php

namespace SPTK;

use \SPTK\SDLWrapper\SDL;

class Clipboard {

  public static function set(string $value): void {
    $sdl = SDL::$instance->sdl;
    $len = strlen($value);
    $text = \FFI::new('char[' . ($len + 1) . ']');
    \FFI::memcpy($text, $value, $len);
    $text[$len] = "\0";
    $sdl->SDL_SetClipboardText($text);
  }

  public static function get(): string|false {
    $sdl = SDL::$instance->sdl;
    if (!$sdl->SDL_HasClipboardText()) {
      return false;
    }
    $textPtr = $sdl->SDL_GetClipboardText();
    if ($textPtr === null) {
      return false;
    }
    $value = \FFI::string($textPtr);
    $sdl->SDL_free($textPtr);
    return $value;
  }

}
