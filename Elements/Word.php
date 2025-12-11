<?php

namespace SPTK;

class Word extends Element {

  public $value;
  protected $surface;
  protected $rect;

  public function init() {
    $sdl = SDL::$instance->sdl;
    $this->rect = $sdl->new('SDL_FRect');
  }

  public function setValue($value) {
    $this->value = "{$value}";
    $this->draw();
  }

  protected function calculateGeometry() {
    $this->geometry->setInlinePosition($this->ancestor->cursor, $this, $this->ancestor->geometry, $this->style, $this->ancestor->style);
  }

  protected function draw() {
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $this->style->get('fontSize', $this->ancestor->geometry->innerHeight));
    $ttf = TTF::$instance->ttf;
    $color = $this->style->get('color');
    $sdlColor = $ttf->new("SDL_Color");
    $sdlColor->r = $color[0];
    $sdlColor->g = $color[1];
    $sdlColor->b = $color[2];
    $sdlColor->a = $color[3] ?? 0xff;

    $bgcolor = $this->ancestor->style->get('backgroundColor');
    $sdlBgColor = $ttf->new("SDL_Color");
    $sdlBgColor->r = $bgcolor[0];
    $sdlBgColor->g = $bgcolor[1];
    $sdlBgColor->b = $bgcolor[2];
    $sdlBgColor->a = $bgcolor[3] ?? 0xff;

//    $this->surface = $ttf->TTF_RenderText_Blended($font->font, $this->value, strlen($this->value), $sdlColor);
    $this->surface = $ttf->TTF_RenderText_Shaded($font->font, $this->value, strlen($this->value), $sdlColor, $sdlBgColor);

    $this->geometry->width = $this->surface->w;
    $this->geometry->height = $this->surface->h;
    $this->geometry->ascent = $font->ascent;
    $this->geometry->descent = $font->descent;
    $this->geometry->setCalculatedSize();

    $sdl = SDL::$instance->sdl;
    $surface = $sdl->cast("SDL_Surface *", $this->surface);
    $this->texture = $sdl->SDL_CreateTextureFromSurface($this->renderer, $surface);
  }

  public function __destruct() {
    $ttf = TTF::$instance->ttf;
    $ttf->SDL_DestroySurface($this->surface);
  }

  protected function render($ptmpTexture) {
    $this->rect->x = $this->geometry->x;
    $this->rect->y = $this->geometry->y;
    $this->rect->w = $this->geometry->width;
    $this->rect->h = $this->geometry->height;
    $ptmpTexture->copy($this->texture, $this->rect);
    return false;
  }

}
