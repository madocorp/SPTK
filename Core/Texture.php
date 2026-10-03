<?php

namespace SPTK\Core;

use SPTK\SDLWrapper\SDL;

/** Represents a renderer-owned sprite or writable layer with local pixel operations. */
final class Texture {

  private SDL $sdl;

  /** Accept a native texture allocated by its texture context. */
  public function __construct(
    private TextureContext $context,
    private ?\FFI\CData $texture,
    private int $width,
    private int $height,
    private bool $writable
  ) {
    $this->sdl = \SPTK\App::sdl();
  }

  /** Release the owned native texture when its PHP wrapper is discarded. */
  public function __destruct() {
    $this->destroy();
  }

  /** Return the texture width in pixels. */
  public function width(): int {
    return $this->width;
  }

  /** Return the texture height in pixels. */
  public function height(): int {
    return $this->height;
  }

  /** Report whether this texture was released or its window closed. */
  public function destroyed(): bool {
    return $this->texture === null;
  }

  /** Release this texture early; repeated calls are harmless. */
  public function destroy(): void {
    if ($this->texture !== null) {
      $this->sdl->ffi->SDL_DestroyTexture($this->texture);
      $this->texture = null;
    }
  }

  /** Return the live native handle for renderer integration. */
  public function handle(): \FFI\CData {
    if ($this->texture === null) {
      throw new \LogicException('Texture has been destroyed.');
    }
    return $this->texture;
  }

  /** Return a live handle only for textures that support drawing. */
  public function writableHandle(): \FFI\CData {
    $handle = $this->handle();
    if (!$this->writable) {
      throw new \LogicException('Imported textures are read-only; copy to a writable texture first.');
    }
    return $handle;
  }

  /** Replace all pixels, premultiplying transparent clear colors for later composition. */
  public function clear(Color|string $color = 'transparent'): void {
    $rgba = $this->color($color);
    for ($i = 0; $i < 3; $i++) {
      $rgba[$i] = (int)round($rgba[$i] * $rgba[3] / 255);
    }
    $this->context->draw($this, 'SDL_RenderClear', [], $rgba);
  }

  /** Alpha-blend a filled rectangle into this layer. */
  public function fillRect(int $x, int $y, int $width, int $height, Color|string $color): void {
    $rect = $this->rect($x, $y, $width, $height);
    $this->context->draw($this, 'SDL_RenderFillRect', [\FFI::addr($rect)], $this->color($color));
  }

  /** Draw a one-pixel rectangle outline into this layer. */
  public function drawRect(int $x, int $y, int $width, int $height, Color|string $color): void {
    $rect = $this->rect($x, $y, $width, $height);
    $this->context->draw($this, 'SDL_RenderRect', [\FFI::addr($rect)], $this->color($color));
  }

  /** Draw a one-pixel line including its endpoints. */
  public function drawLine(int $x1, int $y1, int $x2, int $y2, Color|string $color): void {
    $this->context->draw($this, 'SDL_RenderLine', [$x1, $y1, $x2, $y2], $this->color($color));
  }

  /** Copy the entire sprite onto a writable texture at native size. */
  public function copyTo(Texture $target, int $x, int $y): void {
    $this->copy($target, 0, 0, $x, $y, $this->width, $this->height);
  }

  /** Copy a sprite region to a local destination, optionally scaled with nearest filtering. */
  public function copy(Texture $target, int $sourceX, int $sourceY, int $targetX, int $targetY, int $width, int $height, ?int $targetWidth = null, ?int $targetHeight = null): void {
    if ($this->context !== $target->context || $this === $target) {
      throw new \InvalidArgumentException('Copy requires different textures from the same window.');
    }
    $handle = $this->handle();
    $src = $this->rect($sourceX, $sourceY, $width, $height);
    $dst = $this->rect($targetX, $targetY, $targetWidth ?? $width, $targetHeight ?? $height);
    if ($src->w === 0 || $src->h === 0 || $dst->w === 0 || $dst->h === 0) {
      return;
    }
    $left = max(0, $sourceX);
    $top = max(0, $sourceY);
    $right = min($this->width, $sourceX + $width);
    $bottom = min($this->height, $sourceY + $height);
    if ($right <= $left || $bottom <= $top) {
      return;
    }
    $dst->x += ($left - $sourceX) * $dst->w / $src->w;
    $dst->y += ($top - $sourceY) * $dst->h / $src->h;
    $dst->w *= ($right - $left) / $src->w;
    $dst->h *= ($bottom - $top) / $src->h;
    $src->x = $left;
    $src->y = $top;
    $src->w = $right - $left;
    $src->h = $bottom - $top;
    $target->context->draw($target, 'SDL_RenderTexture', [$handle, \FFI::addr($src), \FFI::addr($dst)]);
  }

  /** Build an SDL rectangle while rejecting negative dimensions. */
  private function rect(int $x, int $y, int $width, int $height): \FFI\CData {
    if ($width < 0 || $height < 0) {
      throw new \InvalidArgumentException('Texture rectangle dimensions cannot be negative.');
    }
    $rect = $this->sdl->ffi->new('SDL_FRect');
    $rect->x = $x;
    $rect->y = $y;
    $rect->w = $width;
    $rect->h = $height;
    return $rect;
  }

  /** Parse RGB, RGBA, and transparent colors without extending grid color semantics. */
  private function color(Color|string $color): array {
    if ($color instanceof Color) {
      return [$color->r, $color->g, $color->b, 255];
    }
    if ($color === 'transparent') {
      return [0, 0, 0, 0];
    }
    if (!preg_match('/^#?([a-f0-9]{6})([a-f0-9]{2})?$/iD', $color, $match)) {
      throw new \InvalidArgumentException('Texture color must be RGB, RGBA, or transparent.');
    }
    $rgb = hexdec($match[1]);
    return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255, isset($match[2]) ? hexdec($match[2]) : 255];
  }

}
