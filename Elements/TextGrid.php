<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\Texture;
use \SPTK\Border;
use \SPTK\Scrollbar;
use \SPTK\SDLWrapper\SDL;
use \SPTK\SDLWrapper\TTF;

class TextGrid extends Element {

  const GLYPH_MAP_SIZE = 64;
  const MAP_PAD = 1;

  private static $fgColor = false;
  private static $sdlRect = false;
  private static $sdlRectAddr = false;
  private static $sdlFRect1 = false;
  private static $sdlFRect1Addr = false;
  private static $sdlFRect2 = false;
  private static $sdlFRect2Addr = false;
  private static $glyphCache = [];
  private static $nextGlyph = [];
  private static $atlas = [];

  protected $font;
  protected $letterWidth;
  protected $letterHeight;
  protected $lineHeight;
  protected $lineOffset;
  protected $textureWidth;
  protected $textureHeight;
  protected $cells = [];
  protected $cellOffsetX = 0;
  protected $cellOffsetY = 0;

  protected function init(): void {
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $this->font = new Font($fontName, $fontSize);
    $this->letterWidth = $this->font->letterWidth;
    $this->letterHeight = $this->font->letterHeight;
    $this->lineHeight = $this->font->height;
    $this->lineOffset = $this->font->height - $this->font->letterHeight;
    if ($this->renderer === false || TTF::$instance === null || SDL::$instance === null) {
      return;
    }
    $ttf = TTF::$instance->ttf;
    if (self::$fgColor === false) {
      self::$fgColor = $ttf->new("SDL_Color");
    }
    $sdl = SDL::$instance->sdl;
    if (self::$sdlRect === false) {
      self::$sdlRect = $sdl->new('SDL_Rect');
      self::$sdlRectAddr = \FFI::addr(self::$sdlRect);
      self::$sdlFRect1 = $sdl->new('SDL_FRect');
      self::$sdlFRect1Addr = \FFI::addr(self::$sdlFRect1);
      self::$sdlFRect2 = $sdl->new('SDL_FRect');
      self::$sdlFRect2Addr = \FFI::addr(self::$sdlFRect2);
    }
    $this->ensureAtlas();
  }

  protected function fontKey(): string {
    return $this->style->get('font') . ':' . $this->style->get('fontSize');
  }

  protected function ensureAtlas(): void {
    $key = $this->fontKey();
    if (isset(self::$atlas[$key])) {
      return;
    }
    $sdl = SDL::$instance->sdl;
    $aw = ($this->letterWidth + self::MAP_PAD * 2) * self::GLYPH_MAP_SIZE;
    $ah = ($this->lineHeight + self::MAP_PAD * 2) * self::GLYPH_MAP_SIZE;
    self::$atlas[$key] = $sdl->SDL_CreateTexture(
      $this->renderer,
      SDL::SDL_PIXELFORMAT_RGBA8888,
      SDL::SDL_TEXTUREACCESS_STATIC,
      $aw,
      $ah
    );
    $zeroPixels = \FFI::new("uint8_t[" . ($aw * $ah * 4) . "]");
    $sdl->SDL_UpdateTexture(self::$atlas[$key], null, $zeroPixels, $aw * 4);
    $sdl->SDL_SetTextureBlendMode(self::$atlas[$key], SDL::SDL_BLENDMODE_BLEND);
    $sdl->SDL_SetTextureScaleMode(self::$atlas[$key], SDL::SDL_SCALE_MODE_NEAREST);
    self::$glyphCache[$key] = [];
    self::$nextGlyph[$key] = 0;
  }

  public function setCells(array $cells, int $offsetX = 0, int $offsetY = 0): void {
    $this->cells = $cells;
    $this->cellOffsetX = $offsetX;
    $this->cellOffsetY = $offsetY;
    $this->changed = true;
  }

  protected function calculateHeights(): void {
    if ($this->display === false) {
      return;
    }
    $this->geometry->setDerivedHeights();
    $this->geometry->setContentHeight($this->lineHeight, $this->geometry->contentHeight);
  }

  protected function layout(): void {
    if ($this->display === false) {
      return;
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
  }

  protected function draw(): void {
    if (
      $this->texture === false ||
      $this->textureWidth !== $this->geometry->width ||
      $this->textureHeight !== $this->geometry->height
    ) {
      $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $this->style->get('backgroundColor'));
      $this->textureWidth = $this->geometry->width;
      $this->textureHeight = $this->geometry->height;
    }
    $this->texture->activate();
    $sdl = SDL::$instance->sdl;
    $background = $this->style->get('backgroundColor');
    $sdl->SDL_SetRenderDrawColor($this->renderer, $background[0], $background[1], $background[2], $background[3] ?? 0xff);
    $sdl->SDL_RenderClear($this->renderer);
    $this->drawCells();
    $this->changed = false;
  }

  protected function drawCells(): void {
    $sdl = SDL::$instance->sdl;
    $cw = $this->letterWidth;
    $ch = $this->lineHeight;
    $previousColor = false;
    foreach ($this->cells as $i => $row) {
      foreach ($row as $j => $cell) {
        $bg = $cell['bg'];
        if ($bg !== $previousColor) {
          $sdl->SDL_SetRenderDrawColor($this->renderer, $bg[0], $bg[1], $bg[2], $bg[3] ?? 0xff);
          $previousColor = $bg;
        }
        self::$sdlFRect2->x = (float)($j * $cw + $this->geometry->paddingLeft + $this->geometry->borderLeft + $this->cellOffsetX);
        self::$sdlFRect2->y = (float)($i * $ch + $this->geometry->paddingTop + $this->geometry->borderTop + $this->cellOffsetY);
        self::$sdlFRect2->w = (float)$cw;
        self::$sdlFRect2->h = (float)$ch;
        $sdl->SDL_RenderFillRect($this->renderer, self::$sdlFRect2Addr);
      }
    }
    $previousColor = false;
    $key = $this->fontKey();
    foreach ($this->cells as $i => $row) {
      foreach ($row as $j => $cell) {
        $glyph = $cell['glyph'];
        if ($glyph === ' ') {
          continue;
        }
        $fg = $cell['fg'];
        if ($fg !== $previousColor) {
          $sdl->SDL_SetTextureColorMod(self::$atlas[$key], $fg[0], $fg[1], $fg[2]);
          $sdl->SDL_SetTextureAlphaMod(self::$atlas[$key], $fg[3] ?? 0xff);
          $previousColor = $fg;
        }
        if (!isset(self::$glyphCache[$key][$glyph])) {
          $this->renderGlyph($glyph);
        }
        $glyphMap = self::$glyphCache[$key][$glyph];
        self::$sdlFRect1->x = (float)$glyphMap[0] - self::MAP_PAD;
        self::$sdlFRect1->y = (float)$glyphMap[1] - self::MAP_PAD + $this->lineOffset;
        self::$sdlFRect1->w = (float)$cw + self::MAP_PAD * 2;
        self::$sdlFRect1->h = (float)$ch + self::MAP_PAD * 2;
        self::$sdlFRect2->x = (float)($j * $cw + $this->geometry->paddingLeft + $this->geometry->borderLeft + $this->cellOffsetX) - self::MAP_PAD;
        self::$sdlFRect2->y = (float)($i * $ch + $this->geometry->paddingTop + $this->geometry->borderTop + $this->cellOffsetY) - self::MAP_PAD;
        self::$sdlFRect2->w = (float)$cw + self::MAP_PAD * 2;
        self::$sdlFRect2->h = (float)$ch + self::MAP_PAD * 2;
        $sdl->SDL_RenderTexture($this->renderer, self::$atlas[$key], self::$sdlFRect1Addr, self::$sdlFRect2Addr);
      }
    }
  }

  protected function renderGlyph(string $glyph): void {
    $key = $this->fontKey();
    $ttf = TTF::$instance->ttf;
    $ttf->TTF_SetFontHinting($this->font->font, TTF::TTF_HINTING_LIGHT_SUBPIXEL);
    $sdl = SDL::$instance->sdl;
    self::$fgColor->r = 0xff;
    self::$fgColor->g = 0xff;
    self::$fgColor->b = 0xff;
    self::$fgColor->a = 0xff;
    $surface = $ttf->TTF_RenderGlyph_Blended($this->font->font, mb_ord($glyph), self::$fgColor);
    $surface2 = \FFI::cast($sdl->type("SDL_Surface*"), $surface);
    $srcSurface = $sdl->SDL_ConvertSurface($surface2, SDL::SDL_PIXELFORMAT_RGBA8888);
    $index = self::$nextGlyph[$key]++;
    $aw = $this->letterWidth;
    $ah = $this->lineHeight;
    $y = (int)($index / self::GLYPH_MAP_SIZE);
    $x = $index % self::GLYPH_MAP_SIZE;
    $ox = 0;
    $oy = 0;
    if ($surface->w != $this->letterWidth || $surface->h != $this->lineHeight) {
      $glyphMetrics = $this->font->glyphMetrics($glyph);
      if ($surface->w != $this->letterWidth && $glyphMetrics[0] < 0) {
        $ox = -$glyphMetrics[0];
      }
      if ($surface->h != $this->lineHeight && $glyphMetrics[3] > $this->font->ascent) {
        $oy = $glyphMetrics[3] - $this->font->ascent;
      }
    }
    self::$sdlRect->x = self::MAP_PAD + $x * ($aw + self::MAP_PAD * 2) - $ox;
    self::$sdlRect->y = self::MAP_PAD + $y * ($ah + self::MAP_PAD * 2) - $oy;
    self::$sdlRect->w = $surface->w;
    self::$sdlRect->h = $surface->h;
    $sdl->SDL_UpdateTexture(self::$atlas[$key], self::$sdlRectAddr, $srcSurface->pixels, $srcSurface->pitch);
    self::$glyphCache[$key][$glyph] = [
      self::MAP_PAD + $x * ($aw + self::MAP_PAD * 2),
      self::MAP_PAD + $y * ($ah + self::MAP_PAD * 2)
    ];
    $ttf->SDL_DestroySurface($surface);
    $sdl->SDL_DestroySurface($surface2);
    $sdl->SDL_DestroySurface($srcSurface);
  }

  protected function render(): Texture|false {
    if ($this->display === false || $this->texture === false) {
      return false;
    }
    new Border($this->texture, $this->geometry, $this->ancestor->geometry, $this->style);
    if ($this->style->get('scrollable')) {
      new Scrollbar($this->texture, $this->scrollX, $this->scrollY, $this->geometry->contentWidth, $this->geometry->contentHeight, $this->geometry, $this->style);
    }
    return $this->texture;
  }

}
