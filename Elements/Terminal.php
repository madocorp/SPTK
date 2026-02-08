<?php

namespace SPTK;

class Terminal extends Element {

  private static $fgColor = false;
  private static $bgColor = false;
  private static $sdlRect = false;
  private static $sdlRectAddr = false;

  protected $buffer;

  public function init() {
    $ttf = TTF::$instance->ttf;
    if (self::$fgColor === false) {
      self::$fgColor = $ttf->new("SDL_Color");
    }
    if (self::$bgColor === false) {
      self::$bgColor = $ttf->new("SDL_Color");
    }
    $sdl = SDL::$instance->sdl;
    if (self::$sdlRect === false) {
      self::$sdlRect = $sdl->new('SDL_Rect');
      self::$sdlRectAddr = \FFI::addr(self::$sdlRect);
    }
  }

  public function setBuffer($buffer) {
    $this->buffer = $buffer;
  }

  protected function draw() {
    $fontName = $this->style->get('font');
    $fontSize = $this->style->get('fontSize', $this->ancestor->geometry);
    if ($fontSize === 0) {
      return;
    }
    $font = new Font($fontName, $fontSize);
    $ttf = TTF::$instance->ttf;
    if (self::$fgColor == false) {
      self::$fgColor = $ttf->new("SDL_Color");
    }
    if (self::$bgColor == false) {
      self::$bgColor = $ttf->new("SDL_Color");
    }

    $sdl = SDL::$instance->sdl;
    $bgcolor = $this->style->get('backgroundColor');
    $surface = $sdl->SDL_CreateSurface($this->geometry->width, $this->geometry->height, SDL::SDL_PIXELFORMAT_RGBA8888);
    $sdl->SDL_FillSurfaceRect($surface, null, 0x000000ff);

    $lines = $this->buffer->getLines();


    foreach ($lines as $i => $row) {
      foreach ($row as $j => $char) {
        $color = $char[Terminal\ScreenBuffer::FG];
        self::$fgColor->r = ($color >> 16) & 0xff;
        self::$fgColor->g = ($color >> 8) & 0xff;
        self::$fgColor->b = $color & 0xff;
        self::$fgColor->a = 0xff;
        $bgcolor = $char[Terminal\ScreenBuffer::BG];
        self::$bgColor->r = ($bgcolor >> 16) & 0xff;
        self::$bgColor->g = ($bgcolor >> 8) & 0xff;
        self::$bgColor->b = $bgcolor & 0xff;
        self::$bgColor->a = 0xff;
        $surfaceL = $ttf->TTF_RenderText_Shaded($font->font, $char[Terminal\ScreenBuffer::CHAR], 1, self::$fgColor, self::$bgColor);
        $srcSurface = \FFI::cast(
          $sdl->type("SDL_Surface*"),
          $surfaceL
        );
        self::$sdlRect->x = $j * 10;
        self::$sdlRect->y = $i * 16;
        self::$sdlRect->w = $surfaceL->w;
        self::$sdlRect->h = $surfaceL->h;
        $sdl->SDL_BlitSurface($srcSurface, null, $surface, self::$sdlRectAddr);
      }
    }
    // create a Texture from the surface
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, [0, 0, 0, 0], $surface);
//    $sdl->SDL_DestroySurface($surface);
//    $sdl->SDL_DestroySurface($surfaceL);
  }

  public function __destruct() {
//    $ttf = TTF::$instance->ttf;
//    $ttf->SDL_DestroySurface($this->surface);
  }

  protected function render() {
    if ($this->display === false) {
      return false;
    }
    if ($this->texture === false) {
      return false;
    }
    new Border($this->texture, $this->geometry, $this->ancestor->geometry, $this->style);
    if ($this->style->get('scrollable')) {
      new Scrollbar($this->texture, $this->scrollX, $this->scrollY, $this->geometry->contentWidth, $this->geometry->contentHeight, $this->geometry, $this->style);
    }
    return $this->texture;
  }

}

