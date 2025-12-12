<?php

namespace SPTK;

class Word extends Element {

  public $value = false;
  protected $surface;

  private static $fgColor;
  private static $bgColor;

  public function setValue($value) {
    $this->value = "{$value}";
    $this->draw();
  }

  protected function calculateGeometry() {
    $this->geometry->setInlinePosition($this->ancestor->cursor, $this, $this->ancestor->geometry, $this->style, $this->ancestor->style);
  }

  protected function draw() {
    $fontName = $this->style->get('font');
    $fontSize = $this->style->get('fontSize', $this->ancestor->geometry->innerHeight);
    $font = new Font($fontName, $fontSize);
    if (empty($this->value)) {
      $this->geometry->ascent = $font->ascent;
      $this->geometry->descent = $font->descent;
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
    $bgcolor = $this->ancestor->style->get('backgroundColor');
    self::$bgColor->r = $bgcolor[0];
    self::$bgColor->g = $bgcolor[1];
    self::$bgColor->b = $bgcolor[2];
    self::$bgColor->a = $bgcolor[3] ?? 0xff;
//    $this->surface = $ttf->TTF_RenderText_Blended($font->font, $this->value, strlen($this->value), $sdlColor);
    $this->surface = $ttf->TTF_RenderText_Shaded($font->font, $this->value, strlen($this->value), self::$fgColor, self::$bgColor);
    $this->geometry->width = $this->surface->w;
    $this->geometry->height = $this->surface->h;
    $this->geometry->ascent = $font->ascent;
    $this->geometry->descent = $font->descent;
    $this->geometry->setCalculatedSize();
    $sdl = SDL::$instance->sdl;
    $surface = $sdl->cast("SDL_Surface *", $this->surface);
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $bgcolor, $surface);
  }

  public function __destruct() {
    $ttf = TTF::$instance->ttf;
    $ttf->SDL_DestroySurface($this->surface);
  }

  protected function render() {
    return $this->texture;
  }

}
