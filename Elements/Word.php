<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\Texture;
use \SPTK\SDLWrapper\TTF;
use \SPTK\SDLWrapper\SDL;

class Word extends Element {

  public $value = false;
  protected $surface;
  protected $width;
  protected $height;
  protected $ascent;
  protected $descent;

  private static $fgColor = false;
  private static $bgColor = false;

  public function setValue($value) {
    $this->value = "{$value}";
    $this->draw();
  }

  public function getWidth() {
    return $this->width;
  }

  protected function measure() {
    $this->windowWidth = $this->ancestor->geometry->windowWidth;
    $this->windowHeight = $this->ancestor->geometry->windowHeight;
    $this->geometry->width = $this->width;
    $this->geometry->height = $this->height;
    $this->geometry->fullWidth = $this->width;
    $this->geometry->fullHeight = $this->height;
    $this->geometry->innerWidth = $this->width;
    $this->geometry->innerHeight = $this->height;
    $this->geometry->ascent = $this->ascent;
    $this->geometry->descent = $this->descent;
    $this->geometry->position = 'inline';
  }

  protected function calculateWidths() {
    ;
  }

  protected function calculateHeights() {
    ;
  }

  protected function layout() {
    ;
  }

  protected function draw() {
    $fontName = $this->style->get('font');
    $fontSize = $this->style->get('fontSize', $this->ancestor->geometry);
    if ($fontSize === 0) {
      return;
    }
    $font = new Font($fontName, $fontSize);
    if ($this->value === false || $this->value === '') {
      $this->texture = false;
      $this->width = 0;
      $this->height = 0;
      $this->ascent = $font->ascent;
      $this->descent = $font->height - $font->ascent;
      return;
    }
    $ttf = TTF::$instance->ttf;
    if (self::$fgColor == false) {
      self::$fgColor = $ttf->new("SDL_Color");
    }
    if (self::$bgColor == false) {
      self::$bgColor = $ttf->new("SDL_Color");
    }
    $color = $this->style->get('color');
    self::$fgColor->r = $color[0];
    self::$fgColor->g = $color[1];
    self::$fgColor->b = $color[2];
    self::$fgColor->a = $color[3] ?? 0xff;
    $bgcolor = $this->style->get('backgroundColor');
    self::$bgColor->r = $bgcolor[0];
    self::$bgColor->g = $bgcolor[1];
    self::$bgColor->b = $bgcolor[2];
    self::$bgColor->a = $bgcolor[3] ?? 0xff;
//    $this->surface = $ttf->TTF_RenderText_Blended($font->font, $this->value, strlen($this->value), $sdlColor);
    $this->surface = $ttf->TTF_RenderText_Shaded($font->font, $this->value, strlen($this->value), self::$fgColor, self::$bgColor);
    $this->width = $this->surface->w;
    $this->height = $this->surface->h;
    $this->ascent = $font->ascent;
    $this->descent = $font->height - $font->ascent;
    $sdl = SDL::$instance->sdl;
    $surface = $sdl->cast("SDL_Surface *", $this->surface);
    $this->texture = new Texture($this->renderer, $this->width, $this->height, $bgcolor, $surface);
  }

  public function __destruct() {
    $ttf = TTF::$instance->ttf;
    $ttf->SDL_DestroySurface($this->surface);
  }

  protected function render() {
    return $this->texture;
  }

}
