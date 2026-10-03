<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Rendering\FontFinder;
use SPTK\SDLWrapper\{SDL, TTF};

/** Resolves rich-text faces and measures and renders them through shared SDL_ttf bindings. */
final class Fonts {

  private static ?SDL $sdl = null;
  private static ?TTF $ttf = null;
  private static bool $ownsTtf = false;
  private static array $paths = [];
  private static array $handles = [];
  private static array $metrics = [];
  private static array $lineMetrics = [];
  private static ?\FFI\CData $measuredWidth = null;
  private static ?\FFI\CData $measuredHeight = null;
  private static ?\FFI\CData $color = null;

  /** Select one font file and point size for a resolved text style. */
  public function face(array $style, int $width, int $height): array {
    $family = $style['fontFamily'];
    $family = is_array($family) ? implode(',', $family) : (string)$family;
    $weight = ($style['bold'] || $style['fontWeight'] === 'bold') ? 'bold' : 'regular';
    $slant = ($style['italic'] || $style['fontStyle'] === 'italic') ? 'italic' : 'roman';
    $key = $family . ':' . $weight . ':' . $slant;
    if (!isset(self::$paths[$key])) {
      self::$paths[$key] = FontFinder::find(is_file($family) ? $family : $family . ':weight=' . $weight . ':slant=' . $slant);
    }
    return [self::$paths[$key], max(1, Format::dimension($style['fontSize'], $width, $height))];
  }

  /** Measure the surface width with the same engine that will paint the text. */
  public function measure(string $text, array $face): array {
    $key = implode(':', $face) . ':' . $text;
    if (isset(self::$metrics[$key])) {
      return self::$metrics[$key];
    }
    $font = $this->handle($face);
    if (!self::$ttf->ffi->TTF_GetStringSize($font, $text, strlen($text), \FFI::addr(self::$measuredWidth), \FFI::addr(self::$measuredHeight))) {
      throw new \RuntimeException('TTF_GetStringSize failed: ' . self::$sdl->error());
    }
    [$ascent, $descent] = $this->lineMetrics($face);
    if (count(self::$metrics) >= 8192) {
      self::$metrics = [];
    }
    return self::$metrics[$key] = [self::$measuredWidth->cdata, $ascent, $descent];
  }

  /** Return stable line metrics for one SDL_ttf face. */
  public function lineMetrics(array $face): array {
    $key = implode(':', $face);
    if (!isset(self::$lineMetrics[$key])) {
      $font = $this->handle($face);
      $ascent = max(0, self::$ttf->ffi->TTF_GetFontAscent($font));
      $height = max(1, self::$ttf->ffi->TTF_GetFontHeight($font));
      self::$lineMetrics[$key] = [$ascent, max(0, $height - $ascent)];
    }
    return self::$lineMetrics[$key];
  }

  /** Render a single styled run as an SDL surface owned by the caller. */
  public function render(string $text, array $face, array $rgba): \FFI\CData {
    $font = $this->handle($face);
    self::$color->r = $rgba[0];
    self::$color->g = $rgba[1];
    self::$color->b = $rgba[2];
    self::$color->a = $rgba[3];
    $surface = self::$ttf->ffi->TTF_RenderText_Blended($font, $text, strlen($text), self::$color);
    if ($surface === null) {
      throw new \RuntimeException('TTF_RenderText_Blended failed: ' . self::$sdl->error());
    }
    return self::$sdl->ffi->cast('SDL_Surface *', $surface);
  }

  /** Return the shared SDL binding used for surface composition. */
  public function sdl(): SDL {
    self::initialize();
    return self::$sdl;
  }

  /** Release cached font handles before their SDL_ttf binding closes. */
  public static function release(): void {
    if (self::$ttf === null) {
      return;
    }
    foreach (self::$handles as $handle) {
      self::$ttf->ffi->TTF_CloseFont($handle);
    }
    self::$handles = [];
    self::$metrics = [];
    self::$lineMetrics = [];
    if (self::$ownsTtf) {
      self::$ttf->ffi->TTF_Quit();
    }
    self::$measuredWidth = null;
    self::$measuredHeight = null;
    self::$color = null;
    self::$ownsTtf = false;
    self::$ttf = null;
    self::$sdl = null;
  }

  /** Open a font once for each file and point size. */
  private function handle(array $face): \FFI\CData {
    self::initialize();
    $key = implode(':', $face);
    if (!isset(self::$handles[$key])) {
      $font = self::$ttf->ffi->TTF_OpenFont($face[0], (float)$face[1]);
      if ($font === null) {
        throw new \RuntimeException('TTF_OpenFont failed: ' . self::$sdl->error());
      }
      self::$handles[$key] = $font;
    }
    return self::$handles[$key];
  }

  /** Reuse the application's FFI objects, with one fallback pair for standalone tests. */
  private static function initialize(): void {
    if (self::$ttf !== null) {
      return;
    }
    $appTtf = \SPTK\App::ttf();
    self::$sdl = \SPTK\App::sdl() ?? new SDL();
    self::$ttf = $appTtf ?? new TTF();
    self::$ownsTtf = $appTtf === null;
    if (self::$ownsTtf && !self::$ttf->ffi->TTF_Init()) {
      throw new \RuntimeException('TTF_Init failed: ' . self::$sdl->error());
    }
    self::$measuredWidth = self::$ttf->ffi->new('int');
    self::$measuredHeight = self::$ttf->ffi->new('int');
    self::$color = self::$ttf->ffi->new('SDL_Color');
  }

}
