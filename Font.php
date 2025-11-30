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

  public function renderText($renderer, $text, $color) {
    $ttf = TTF::$instance->ttf;
    $sdl = SDL::$instance->sdl;
    $sdlColor = $ttf->new("SDL_Color");
    $sdlColor->r = $color[0];
    $sdlColor->g = $color[1];
    $sdlColor->b = $color[2];
    $sdlColor->a = $color[3] ?? 0xff;
    $surface = $ttf->TTF_RenderText_Blended($this->font, $text, strlen($text), $sdlColor);
    $w = $surface->w;
    $h = $surface->h;
    $sdlSurface = $sdl->cast("SDL_Surface *", $surface);
    $texture = $sdl->SDL_CreateTextureFromSurface($renderer, $sdlSurface);
    $sdl->SDL_DestroySurface($sdlSurface);
    return ['texture' => $texture, 'width' => $w, 'height' => $h];
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
