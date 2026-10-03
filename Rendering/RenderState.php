<?php

namespace SPTK\Rendering;

use SPTK\SDLWrapper\SDL;

/** Saves and restores renderer state around application canvas drawing. */
final class RenderState {

  private SDL $sdl;
  private ?\FFI\CData $target;
  private \FFI\CData $viewport;
  private \FFI\CData $clip;
  private \FFI\CData $color;
  private \FFI\CData $blend;
  private bool $viewportSet;
  private bool $clipEnabled;

  /** Capture the target, viewport, clipping, color, and draw blend mode. */
  public function __construct(private \FFI\CData $renderer) {
    $this->sdl = \SPTK\App::sdl();
    $ffi = $this->sdl->ffi;
    $this->target = $ffi->SDL_GetRenderTarget($renderer);
    $this->viewport = $ffi->new('SDL_Rect');
    $this->clip = $ffi->new('SDL_Rect');
    $this->color = $ffi->new('Uint8[4]');
    $this->blend = $ffi->new('int');
    $this->viewportSet = $ffi->SDL_RenderViewportSet($renderer);
    $this->clipEnabled = $ffi->SDL_RenderClipEnabled($renderer);
    $this->sdl->checkReturnValue($ffi->SDL_GetRenderViewport($renderer, \FFI::addr($this->viewport)), 'SDL_GetRenderViewport');
    $this->sdl->checkReturnValue($ffi->SDL_GetRenderClipRect($renderer, \FFI::addr($this->clip)), 'SDL_GetRenderClipRect');
    $this->sdl->checkReturnValue($ffi->SDL_GetRenderDrawColor($renderer, \FFI::addr($this->color[0]), \FFI::addr($this->color[1]), \FFI::addr($this->color[2]), \FFI::addr($this->color[3])), 'SDL_GetRenderDrawColor');
    $this->sdl->checkReturnValue($ffi->SDL_GetRenderDrawBlendMode($renderer, \FFI::addr($this->blend)), 'SDL_GetRenderDrawBlendMode');
  }

  /** Restore state even when an application's painter throws. */
  public function restore(): void {
    $ffi = $this->sdl->ffi;
    $this->sdl->checkReturnValue($ffi->SDL_SetRenderTarget($this->renderer, $this->target), 'SDL_SetRenderTarget');
    $this->sdl->checkReturnValue($ffi->SDL_SetRenderViewport($this->renderer, $this->viewportSet ? \FFI::addr($this->viewport) : null), 'SDL_SetRenderViewport');
    $this->sdl->checkReturnValue($ffi->SDL_SetRenderClipRect($this->renderer, $this->clipEnabled ? \FFI::addr($this->clip) : null), 'SDL_SetRenderClipRect');
    $this->sdl->checkReturnValue($ffi->SDL_SetRenderDrawColor($this->renderer, $this->color[0], $this->color[1], $this->color[2], $this->color[3]), 'SDL_SetRenderDrawColor');
    $this->sdl->checkReturnValue($ffi->SDL_SetRenderDrawBlendMode($this->renderer, $this->blend->cdata), 'SDL_SetRenderDrawBlendMode');
  }

}
