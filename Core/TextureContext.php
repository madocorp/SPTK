<?php

namespace SPTK\Core;

use SPTK\Rendering\RenderState;
use SPTK\SDLWrapper\SDL;

/** Creates and owns application textures for one window's renderer. */
final class TextureContext {

  private SDL $sdl;
  private \WeakMap $textures;
  private bool $closed = false;
  private ?Texture $activeTarget = null;

  /** Bind texture allocation to a window renderer. */
  public function __construct(private \FFI\CData $renderer) {
    $this->sdl = \SPTK\App::sdl();
    $this->textures = new \WeakMap();
  }

  /** Create a writable layer, transparent unless a background is supplied. */
  public function createTexture(int $width, int $height, Color|string $background = 'transparent'): Texture {
    $texture = $this->allocate($width, $height, true);
    $texture->clear($background);
    return $texture;
  }

  /** Upload a file, GD image, or decoded raster as a reusable read-only sprite. */
  public function textureFromImage(string|\GdImage|RasterImage $source): Texture {
    $this->assertOpen();
    $image = $source instanceof RasterImage ? $source : ImageSource::from($source)->raster();
    $texture = $this->allocate($image->width, $image->height, false);
    try {
      $data = $image->pixels;
      $bytes = \FFI::new('char[' . strlen($data) . ']');
      \FFI::memcpy($bytes, $data, strlen($data));
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_UpdateTexture($texture->handle(), null, $bytes, $image->width * 4), 'SDL_UpdateTexture');
      return $texture;
    } catch (\Throwable $error) {
      $texture->destroy();
      throw $error;
    }
  }

  /** Execute one drawing operation with renderer state restored afterward. */
  public function draw(Texture $target, string $operation, array $arguments, ?array $color = null): void {
    $this->assertOpen();
    $target->writableHandle();
    if ($target === $this->activeTarget) {
      $this->execute($operation, $arguments, $color);
      return;
    }
    $this->withTarget($target, $this->execute(...), [$operation, $arguments, $color]);
  }

  /** Batch drawing to a texture while preserving renderer state around the entire callback. */
  public function withTarget(Texture $target, callable $draw, array $arguments = []): void {
    $this->assertOpen();
    $handle = $target->writableHandle();
    $ffi = $this->sdl->ffi;
    $state = new RenderState($this->renderer);
    $previous = $this->activeTarget;
    try {
      $this->sdl->checkReturnValue($ffi->SDL_SetRenderTarget($this->renderer, $handle), 'SDL_SetRenderTarget');
      $this->sdl->checkReturnValue($ffi->SDL_SetRenderViewport($this->renderer, null), 'SDL_SetRenderViewport');
      $this->sdl->checkReturnValue($ffi->SDL_SetRenderClipRect($this->renderer, null), 'SDL_SetRenderClipRect');
      $this->sdl->checkReturnValue($ffi->SDL_SetRenderDrawBlendMode($this->renderer, SDL::SDL_BLENDMODE_BLEND), 'SDL_SetRenderDrawBlendMode');
      $this->activeTarget = $target;
      $draw(...$arguments);
    } finally {
      $this->activeTarget = $previous;
      $state->restore();
    }
  }

  /** Release all associated textures and reject further allocations. */
  public function close(): void {
    if ($this->closed) {
      return;
    }
    foreach ($this->textures as $texture => $unused) {
      $texture->destroy();
    }
    $this->closed = true;
  }

  /** Allocate and configure a crisp sprite or premultiplied writable layer. */
  private function allocate(int $width, int $height, bool $writable): Texture {
    $this->assertOpen();
    if ($width < 1 || $height < 1) {
      throw new \InvalidArgumentException('Texture dimensions must be positive.');
    }
    $ffi = $this->sdl->ffi;
    $handle = $ffi->SDL_CreateTexture($this->renderer, SDL::SDL_PIXELFORMAT_RGBA8888, $writable ? SDL::SDL_TEXTUREACCESS_TARGET : SDL::SDL_TEXTUREACCESS_STATIC, $width, $height);
    if ($handle === null) {
      throw new \RuntimeException('Texture creation failed: ' . $this->sdl->error());
    }
    try {
      $blend = $writable ? SDL::SDL_BLENDMODE_BLEND_PREMULTIPLIED : SDL::SDL_BLENDMODE_BLEND;
      $this->sdl->checkReturnValue($ffi->SDL_SetTextureBlendMode($handle, $blend), 'SDL_SetTextureBlendMode');
      $this->sdl->checkReturnValue($ffi->SDL_SetTextureScaleMode($handle, SDL::SDL_SCALE_MODE_NEAREST), 'SDL_SetTextureScaleMode');
      $texture = new Texture($this, $handle, $width, $height, $writable);
      $this->textures[$texture] = true;
      return $texture;
    } catch (\Throwable $error) {
      $ffi->SDL_DestroyTexture($handle);
      throw $error;
    }
  }

  /** Reject access after the owning renderer has closed. */
  private function assertOpen(): void {
    if ($this->closed) {
      throw new \LogicException('Texture context belongs to a closed window.');
    }
  }

  /** Submit a command without switching the already selected drawing target. */
  private function execute(string $operation, array $arguments, ?array $color): void {
    $ffi = $this->sdl->ffi;
    if ($color !== null) {
      $this->sdl->checkReturnValue($ffi->SDL_SetRenderDrawColor($this->renderer, ...$color), 'SDL_SetRenderDrawColor');
    }
    $this->sdl->checkReturnValue($ffi->$operation($this->renderer, ...$arguments), $operation);
  }

}
