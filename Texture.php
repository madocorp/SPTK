<?php

namespace SPTK;

class Texture {

  protected $texture;
  protected $sdl;
  protected $ttf;
  protected $renderer;
  protected $width;
  protected $height;

  public function __construct($renderer, $width, $height, $color) {
    $this->sdl = SDL::$instance->sdl;
    $this->ttf = TTF::$instance->ttf;
    $this->renderer = $renderer;
    $this->width = $width;
    $this->height = $height;
    $this->texture = $this->sdl->SDL_CreateTexture($this->renderer, SDL::SDL_PIXELFORMAT_RGBA8888, SDL::SDL_TEXTUREACCESS_TARGET, $this->width, $this->height);
    $this->sdl->SDL_SetTextureBlendMode($this->texture, SDL::SDL_BLENDMODE_BLEND);
    $this->sdl->SDL_SetTextureScaleMode($this->texture, SDL::SDL_SCALE_MODE_NEAREST);
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, $color[0], $color[1], $color[2], $color[3] ?? 0xff);
    $this->sdl->SDL_RenderClear($this->renderer);
  }

  public function __destruct() {
    $this->sdl->SDL_DestroyTexture($this->texture);
  }

  public function drawImage($image, $x, $y) {

  }

  public function drawLine($x1, $y1, $x2, $y2, $color) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, $color[0], $color[1], $color[2], $color[3] ?? 0xff);
    $this->sdl->SDL_RenderLine($this->renderer, $x1, $y1, $x2, $y2);
  }

  public function copyTo($target, $x, $y) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $target->texture ?? null);
    $destRect = $this->sdl->new('SDL_FRect');
    $destRect->x = $x;
    $destRect->y = $y;
    $destRect->w = $this->width;
    $destRect->h = $this->height;
    $this->sdl->SDL_RenderTexture($this->renderer, $this->texture, null, \FFI::addr($destRect));
  }

  public function copy($texture, $rect) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_RenderTexture($this->renderer, $texture, null, \FFI::addr($rect));
  }

}
