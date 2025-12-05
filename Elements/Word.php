<?php

namespace SPTK;

class Word extends Element {

  public $value;
  protected $surface;

  public function setValue($value) {
    $this->value = "{$value}";
    $this->draw();
  }

  protected function calculateGeometry($cursor) {
    $this->geometry->setInlinePosition($cursor, $this->ancestor->geometry, $this->style, $this->ancestor->style);
  }

  protected function draw() {
    $font = new Font("LiberationMono-Bold.ttf", $this->style->get('fontSize', $this->ancestor->geometry->innerHeight));
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
  }

  public function __destruct() {
    $ttf = TTF::$instance->ttf;
    $ttf->SDL_DestroySurface($this->surface);
  }

  protected function render($ptmpTexture) {
    $sdl = SDL::$instance->sdl;
    $surface = $sdl->cast("SDL_Surface *", $this->surface);
    $texture = $sdl->SDL_CreateTextureFromSurface($this->renderer, $surface);
    $destRect = $sdl->new('SDL_FRect');
    $destRect->x = $this->geometry->x;
    $destRect->y = $this->geometry->y;
    $destRect->w = $this->geometry->width;
    $destRect->h = $this->geometry->height;
    $ptmpTexture->copy($texture, $destRect);
    return false;
  }

}
