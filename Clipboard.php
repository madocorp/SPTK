<?php

namespace SPTK;

class Clipboard {

  public static function set($value) {
    $sdl = SDL::$instance->sdl;
    $len = strlen($value);
    $text = \FFI::new('char[' . ($len + 1) . ']');
    \FFI::memcpy($text, $value, $len);
    $text[$len] = "\0";
    $sdl->SDL_SetClipboardText($text);
  }

  public static function get() {
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
