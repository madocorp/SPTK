<?php

namespace SPTK;

class Clipboard {

  const COPY = 1;
  const CUT = 2;
  const PASTE = 3;


  public static function processEvent($event, $copy, &$paste) {
    $paste = false;
    if (($event['mod'] & KeyModifier::CTRL) == 0) {
      return false;
    }
    if ($event['scancode'] == ScanCode::C) {
      self::set($copy);
      return self::COPY;
    }
    if ($event['scancode'] == ScanCode::X) {
      self::set($copy);
      return self::CUT;
    }
    if ($event['scancode'] == ScanCode::V) {
      $paste = self::get();
      if ($paste == false) {
        return false;
      }
      return self::PASTE;
    }
    return false;
  }

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

