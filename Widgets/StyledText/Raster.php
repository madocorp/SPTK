<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Core\RasterImage;
use SPTK\SDLWrapper\SDL;

/** Composes SDL_ttf run surfaces into one clipped, tile-sized SDL surface. */
final class Raster {

  private Fonts $fonts;
  private SDL $sdl;
  private \FFI\CData $rect;

  /** Reuse SDL bindings and the mutable destination rectangle across run draws. */
  public function __construct() {
    $this->fonts = new Fonts();
    $this->sdl = $this->fonts->sdl();
    $this->rect = $this->sdl->ffi->new('SDL_Rect');
  }

  /** Measure wrapped text height; the layout owns all box edges. */
  public function contentHeight(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): int {
    [$lines, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
    return $this->height($lines, $gap);
  }

  /** Render styled text through SDL_ttf and return packed RGBA pixels. */
  public function render(array $runs, array $style, int $width, int $height, int $referenceWidth, int $referenceHeight): RasterImage {
    if ($width < 1 || $height < 1) {
      throw new \InvalidArgumentException('Styled text raster dimensions must be positive.');
    }
    $ffi = $this->sdl->ffi;
    $canvas = $ffi->SDL_CreateSurface($width, $height, SDL::SDL_PIXELFORMAT_RGBA8888);
    if ($canvas === null) {
      throw new \RuntimeException('SDL_CreateSurface failed: ' . $this->sdl->error());
    }
    try {
      $background = $this->rgba($style['background']);
      $this->sdl->checkReturnValue($ffi->SDL_ClearSurface($canvas, $background[0] / 255, $background[1] / 255, $background[2] / 255, $background[3] / 255), 'SDL_ClearSurface');
      [$lines, $gap] = $this->layout($runs, $style, $width, $referenceWidth, $referenceHeight);
      $extra = max(0, $height - $this->height($lines, $gap));
      $y = match ($style['verticalAlign']) {
        'center' => intdiv($extra, 2), 'bottom' => $extra, default => 0,
      };
      foreach ($lines as $line) {
        if ($y >= $height) {
          break;
        }
        $this->line($canvas, $line, $style, $width, $y);
        $y += $line['ascent'] + $line['descent'] + $gap;
      }
      return new RasterImage($width, $height, $this->pixels($canvas));
    } finally {
      $ffi->SDL_DestroySurface($canvas);
    }
  }

  /** Wrap runs with SDL_ttf widths resolved against the measured tile. */
  private function layout(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): array {
    $lines = (new Lines($this->fonts))->layout($runs, $style, max(1, $width), $referenceWidth, $referenceHeight);
    return [$lines, Format::dimension($style['lineGap'], $referenceWidth, $referenceHeight)];
  }

  /** Sum the stable ascent and descent of each line. */
  private function height(array $lines, int $gap): int {
    $height = max(0, count($lines) - 1) * $gap;
    foreach ($lines as $line) {
      $height += $line['ascent'] + $line['descent'];
    }
    return $height;
  }

  /** Place every run at its shared baseline and let SDL clip to the tile. */
  private function line(\FFI\CData $canvas, array $line, array $style, int $width, int $y): void {
    $extra = max(0, $width - $line['width']);
    $x = match ($style['textAlign']) {
      'center' => intdiv($extra, 2), 'right' => $extra, default => 0,
    };
    foreach ($line['segments'] as $segment) {
      $runStyle = $segment['style'];
      $lineHeight = $line['ascent'] + $line['descent'];
      if ($runStyle['background'] !== 'transparent' && $segment['width'] > 0) {
        $this->fill($canvas, $x, $y, $segment['width'], $lineHeight, $runStyle['background']);
      }
      if ($segment['text'] !== '') {
        $surface = $this->fonts->render($segment['text'], $segment['face'], $this->rgba($runStyle['color']));
        try {
          [$ascent] = $this->fonts->lineMetrics($segment['face']);
          $this->rect->x = $x;
          $this->rect->y = $y + $line['ascent'] - $ascent;
          $this->rect->w = $surface->w;
          $this->rect->h = $surface->h;
          $this->sdl->checkReturnValue($this->sdl->ffi->SDL_BlitSurface($surface, null, $canvas, \FFI::addr($this->rect)), 'SDL_BlitSurface');
        } finally {
          $this->sdl->ffi->SDL_DestroySurface($surface);
        }
      }
      $x += $segment['width'];
    }
  }

  /** Fill an inline run background in the surface's own pixel format. */
  private function fill(\FFI\CData $canvas, int $x, int $y, int $width, int $height, string $color): void {
    $rgba = $this->rgba($color);
    $this->rect->x = $x;
    $this->rect->y = $y;
    $this->rect->w = $width;
    $this->rect->h = $height;
    $pixel = $this->sdl->ffi->SDL_MapSurfaceRGBA($canvas, ...$rgba);
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_FillSurfaceRect($canvas, \FFI::addr($this->rect), $pixel), 'SDL_FillSurfaceRect');
  }

  /** Parse one CSS color into SDL RGBA channels. */
  private function rgba(string $color): array {
    return Format::color($color);
  }

  /** Copy a surface's pixel rows without retaining the native surface. */
  private function pixels(\FFI\CData $surface): string {
    $raw = \FFI::string($this->sdl->ffi->cast('char *', $surface->pixels), $surface->pitch * $surface->h);
    if ($surface->pitch === $surface->w * 4) {
      return $raw;
    }
    $packed = '';
    for ($y = 0; $y < $surface->h; $y++) {
      $packed .= substr($raw, $y * $surface->pitch, $surface->w * 4);
    }
    return $packed;
  }

}
