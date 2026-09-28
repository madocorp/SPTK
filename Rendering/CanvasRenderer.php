<?php

namespace SPTK\Rendering;

use SPTK\Core\{Texture, TextureContext};
use SPTK\Layout\Tile;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Canvas\Canvas;

/** Retains per-window canvas surfaces and owns the application's texture factory. */
final class CanvasRenderer {

  private SDL $sdl;
  private TextureContext $textures;
  private array $targets = [];
  private array $used = [];
  private \WeakMap $subscribed;
  private bool $closed = false;
  private bool $redrawQueued = false;

  /** Attach canvas surfaces and application textures to one native renderer. */
  public function __construct(private \FFI\CData $renderer) {
    $this->sdl = \SPTK\App::sdl();
    $this->textures = new TextureContext($renderer);
    $this->subscribed = new \WeakMap();
  }

  /** Start tracking canvases visible in a complete frame. */
  public function begin(): void {
    $this->used = [];
    $this->redrawQueued = false;
  }

  /** Repaint changed surfaces and composite their shaded result into the window. */
  public function paint(Canvas $canvas, Tile $area, bool $selected): void {
    if ($area->width < 1 || $area->height < 1) {
      return;
    }
    $state = new RenderState($this->renderer);
    try {
      $id = $this->target($canvas, $area);
      $surface = $this->targets[$id]['surface'];
      $revision = $canvas->revision();
      if ($this->targets[$id]['revision'] !== $revision) {
        $this->textures->withTarget($surface, $this->draw(...), [$canvas, $surface]);
        $this->targets[$id]['revision'] = $revision;
      }
    } finally {
      $state->restore();
    }
    $shade = $selected ? 255 : (int)round(255 * 0.75);
    $ffi = $this->sdl->ffi;
    $handle = $surface->handle();
    $this->sdl->checkReturnValue($ffi->SDL_SetTextureColorMod($handle, $shade, $shade, $shade), 'SDL_SetTextureColorMod');
    $rect = $ffi->new('SDL_FRect');
    $rect->x = $area->x;
    $rect->y = $area->y;
    $rect->w = $area->width;
    $rect->h = $area->height;
    try {
      $this->sdl->checkReturnValue($ffi->SDL_RenderTexture($this->renderer, $handle, null, \FFI::addr($rect)), 'SDL_RenderTexture');
    } finally {
      $this->sdl->checkReturnValue($ffi->SDL_SetTextureColorMod($handle, 255, 255, 255), 'SDL_SetTextureColorMod');
    }
  }

  /** Release surfaces no longer visible after a complete frame. */
  public function end(): void {
    foreach ($this->targets as $id => $entry) {
      if (!isset($this->used[$id])) {
        $entry['surface']->destroy();
        unset($this->targets[$id]);
      }
    }
  }

  /** Release all native textures before the owning renderer closes. */
  public function close(): void {
    $this->closed = true;
    $this->textures->close();
    $this->targets = [];
    $this->used = [];
  }

  /** Queue a window redraw when application code invalidates a canvas. */
  public function requestRedraw(): void {
    if ($this->closed || $this->redrawQueued) {
      return;
    }
    $ffi = $this->sdl->ffi;
    $window = $ffi->SDL_GetRenderWindow($this->renderer);
    if ($window === null) {
      return;
    }
    $event = $ffi->new('SDL_Event');
    $event->window->type = SDL::SDL_EVENT_WINDOW_EXPOSED;
    $event->window->windowID = $ffi->SDL_GetWindowID($window);
    $event->window->data1 = SDL::CANVAS_REDRAW_REQUEST;
    $this->sdl->checkReturnValue($ffi->SDL_PushEvent(\FFI::addr($event)), 'SDL_PushEvent');
    $this->redrawQueued = true;
  }

  /** Consume a queued notification and report surfaces still needing a repaint. */
  public function redrawNeeded(): bool {
    $this->redrawQueued = false;
    foreach ($this->targets as $entry) {
      if ($entry['revision'] !== $entry['canvas']->revision()) {
        return true;
      }
    }
    return false;
  }

  /** Clear and paint a surface while its render target remains selected. */
  private function draw(Canvas $canvas, Texture $surface): void {
    $surface->clear($canvas->background());
    $canvas->draw($surface, $this->textures);
  }

  /** Reuse a surface until its widget's pixel dimensions change. */
  private function target(Canvas $canvas, Tile $area): int {
    $id = spl_object_id($canvas);
    if (!isset($this->subscribed[$canvas])) {
      $canvas->on('change', $this->requestRedraw(...));
      $this->subscribed[$canvas] = true;
    }
    $this->used[$id] = true;
    $entry = $this->targets[$id] ?? null;
    if ($entry !== null && ($entry['surface']->destroyed() || $entry['surface']->width() !== $area->width || $entry['surface']->height() !== $area->height)) {
      $entry['surface']->destroy();
      unset($this->targets[$id]);
      $entry = null;
    }
    if ($entry === null) {
      $surface = $this->textures->createTexture($area->width, $area->height);
      $this->targets[$id] = ['canvas' => $canvas, 'surface' => $surface, 'revision' => -1];
    }
    return $id;
  }

}
