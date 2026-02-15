<?php

namespace SPTK;

class Texture {

  protected $texture;
  protected $sdl;
  protected $ttf;
  protected $renderer;
  protected $width;
  protected $height;

  private static $sdlRect;
  private static $sdlRectAddr;

  public static function init() {
    $sdl = SDL::$instance->sdl;
    self::$sdlRect = $sdl->new('SDL_FRect');
    self::$sdlRectAddr = \FFI::addr(self::$sdlRect);
  }

  public function __construct($renderer, $width, $height, $color, $fromSurface = false) {
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
    if ($fromSurface !== false) {
      $tmpTexture = $this->sdl->SDL_CreateTextureFromSurface($this->renderer, $fromSurface);
      $this->sdl->SDL_RenderTexture($this->renderer, $tmpTexture, null, null);
      $this->sdl->SDL_DestroyTexture($tmpTexture);
    }
  }

  public function __destruct() {
    $this->sdl->SDL_DestroyTexture($this->texture);
  }

  public function drawLine($x1, $y1, $x2, $y2, $color) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, $color[0], $color[1], $color[2], $color[3] ?? 0xff);
    $this->sdl->SDL_RenderLine($this->renderer, $x1, $y1, $x2, $y2);
  }

  public function drawRect($x1, $y1, $x2, $y2, $color) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, $color[0], $color[1], $color[2], $color[3] ?? 0xff);
    self::$sdlRect->x = $x1;
    self::$sdlRect->y = $y1;
    self::$sdlRect->w = $x2 - $x1;
    self::$sdlRect->h = $y2 - $y1;
    $this->sdl->SDL_RenderRect($this->renderer, self::$sdlRectAddr);
  }

  public function drawFillRect($x1, $y1, $x2, $y2, $color) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, $color[0], $color[1], $color[2], $color[3] ?? 0xff);
    self::$sdlRect->x = $x1;
    self::$sdlRect->y = $y1;
    self::$sdlRect->w = $x2 - $x1;
    self::$sdlRect->h = $y2 - $y1;
    $this->sdl->SDL_RenderFillRect($this->renderer, self::$sdlRectAddr);
  }

  public function copyTo($target, $x, $y) {
    $this->sdl->SDL_SetRenderTarget($this->renderer, $target->texture ?? null);
    self::$sdlRect->x = $x;
    self::$sdlRect->y = $y;
    self::$sdlRect->w = $this->width;
    self::$sdlRect->h = $this->height;
    $this->sdl->SDL_RenderTexture($this->renderer, $this->texture, null, self::$sdlRectAddr);
  }

  public function copy($texture, $x, $y, $w, $h) {
    self::$sdlRect->x = $x;
    self::$sdlRect->y = $y;
    self::$sdlRect->w = $w;
    self::$sdlRect->h = $h;
    $this->sdl->SDL_SetRenderTarget($this->renderer, $this->texture);
    $this->sdl->SDL_RenderTexture($this->renderer, $texture, null, self::$sdlRectAddr);
  }

}
