<?php

namespace SPTK\Core;

/**
 * Provides toolkit clipboard access with a deterministic in-memory fallback.
 */
class Clipboard {

  protected static mixed $provider = null;
  protected static string $text = '';

  public static function setProvider(?object $provider): void {
    self::$provider = $provider;
  }

  public static function set(string $text): void {
    self::$text = $text;
    if (self::$provider !== null && method_exists(self::$provider, 'set')) {
      self::$provider->set($text);
    }
  }

  public static function get(): string {
    if (self::$provider !== null && method_exists(self::$provider, 'get')) {
      $text = self::$provider->get();
      if (is_string($text)) {
        self::$text = $text;
      }
    }
    return self::$text;
  }

}
