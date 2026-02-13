<?php

namespace SPTK;

class Terminal extends Element {

  private static $fgColor = false;
  private static $bgColor = false;
  private static $sdlRect = false;
  private static $sdlRect2 = false;
  private static $sdlRectF = false;
  private static $sdlRectAddr = false;
  private static $sdlRect2Addr = false;
  private static $sdlRectFAddr = false;
  private static $glyphCache = [];

  protected $buffer;
  protected $font;
  protected $letterWidth;
  protected $linHeight;

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
      self::$sdlRect2 = $sdl->new('SDL_Rect');
      self::$sdlRect2Addr = \FFI::addr(self::$sdlRect2);
      self::$sdlRectF = $sdl->new('SDL_FRect');
      self::$sdlRectFAddr = \FFI::addr(self::$sdlRectF);
    }
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $this->font = new Font($fontName, $fontSize);
    $this->letterWidth = $this->font->letterWidth;
    $this->lineHeight = $this->font->height;
  }

  public function setBuffer($buffer) {
    $this->buffer = $buffer;
  }

  protected function calculateHeights() {
    $rows = $this->buffer->countLines();
    $h = $rows * $this->lineHeight;
    $this->geometry->height = $this->geometry->borderTop + $this->geometry->paddingTop + $h + $this->geometry->paddingBottom + $this->geometry->borderBottom;
    $this->geometry->setDerivedHeights();
    $this->geometry->setContentHeight($this->lineHeight, $this->geometry->borderTop + $this->geometry->paddingTop + $h);
  }

  protected function draw() {
    $ttf = TTF::$instance->ttf;

    $sdl = SDL::$instance->sdl;
    $bgcolor = $this->style->get('backgroundColor');
    $surface = $sdl->SDL_CreateSurface($this->geometry->width, $this->geometry->height, SDL::SDL_PIXELFORMAT_RGBA8888);
    $sdl->SDL_FillSurfaceRect($surface, null, 0x000000ff);

    $lines = $this->buffer->getLines();

    self::$sdlRect2->x = 0;
    self::$sdlRect2->y = 0;
    self::$sdlRect2->w = $this->letterWidth;
    self::$sdlRect2->h = $this->lineHeight;

    foreach ($lines as $i => $row) {
      foreach ($row as $j => $char) {
        $glyph = $char[Terminal\ScreenBuffer::GLYPH];
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
        $surfaceL = $ttf->TTF_RenderText_Shaded($this->font->font, $glyph, strlen($glyph), self::$fgColor, self::$bgColor);
        $srcSurface = \FFI::cast(
          $sdl->type("SDL_Surface*"),
          $surfaceL
        );

        self::$sdlRect2->x = 0;
        self::$sdlRect2->y = 0;
        if ($surfaceL->w != $this->letterWidth) {
          $glyphMetrics = $this->glyphMetrics($glyph);
          if ($glyphMetrics[0] < 0) {
            self::$sdlRect2->x = -$glyphMetrics[0];
          }
        }
        if ($surfaceL->h != $this->lineHeight) {
          $glyphMetrics = $this->glyphMetrics($glyph);
          if ($glyphMetrics[3] > $this->font->ascent) {
            self::$sdlRect2->y = $glyphMetrics[3] - $this->font->ascent;
          }
        }

        self::$sdlRect->x = $j * $this->letterWidth + $this->geometry->paddingLeft + $this->geometry->borderLeft;
        self::$sdlRect->y = $i * $this->lineHeight + $this->geometry->paddingTop + $this->geometry->borderTop ;
        self::$sdlRect->w = $surfaceL->w;
        self::$sdlRect->h = $surfaceL->h;
        $sdl->SDL_BlitSurface($srcSurface, self::$sdlRect2Addr, $surface, self::$sdlRectAddr);
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

  protected function glyphMetrics($char) {
    if (!isset(self::$glyphCache[$char])) {
      $ttf = TTF::$instance->ttf;
      $minx = \FFI::new("int");
      $maxx = \FFI::new("int");
      $miny = \FFI::new("int");
      $maxy = \FFI::new("int");
      $advance = \FFI::new("int");
      $ttf->TTF_GetGlyphMetrics(
        $this->font->font,
        mb_ord($char),
        \FFI::addr($minx),
        \FFI::addr($maxx),
        \FFI::addr($miny),
        \FFI::addr($maxy),
        \FFI::addr($advance)
      );
      self::$glyphCache[$char] = [
        $minx->cdata,
        $maxx->cdata,
        $miny->cdata,
        $maxy->cdata
      ];
    }
    return self::$glyphCache[$char];
  }

}

