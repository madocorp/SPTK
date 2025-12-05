<?php

namespace SPTK;

class Font {

  protected static $fonts = [];

  public $font;
  public $ascent;
  public $descent;

  public function __construct($name, $size) {
    if (!isset(self::$fonts[$name][$size])) {
      $this->open($name, $size);
    }
    $this->font = self::$fonts[$name][$size]['handle'];
    $this->ascent = self::$fonts[$name][$size]['ascent'];
    $this->descent = self::$fonts[$name][$size]['descent'];
  }

  protected function open($name, $size) {
    $ttf = TTF::$instance->ttf;
    $font = $ttf->TTF_OpenFont($name, $size);
    self::$fonts[$name][$size]['handle'] = $font;
    $ascent = $ttf->TTF_GetFontAscent($font);
    $descent = $ttf->TTF_GetFontDescent($font);
    self::$fonts[$name][$size]['ascent'] = $ascent; //->cdata;
    self::$fonts[$name][$size]['descent'] = $descent; //->cdata;
  }

  public static function closeAll() {
    $ttf = TTF::$instance->ttf;
    foreach (self::$fonts as $font) {
      foreach ($font as $size) {
        $ttf->TTF_CloseFont($size['handle']);
      }
    }
  }

}
