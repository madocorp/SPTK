<?php

namespace SPTK\Core;

use SPTK\SDLWrapper\SDL;

/** Owns the physical window, native renderer, character grid, and active screen. */
final class Window {

  private $window;
  private $id;
  private $ffiRenderer;
  private $currentScreen;
  private $screens;
  private $sdl;
  private $font;
  private $gridRenderer;
  private $ffiWidth;
  private $ffiHeight;
  private $width;
  private $height;
  private $columns;
  private $rows;
  private $grid;

  public function __construct(array $data) {
    $this->ffiWidth = \FFI::new('int');
    $this->ffiHeight = \FFI::new('int');
    $this->sdl = \SPTK\App::sdl();
    $this->font = \SPTK\App::font();
    $this->gridRenderer = \SPTK\App::gridRenderer();
    $this->screens = $data['screens'];
    $this->currentScreen = 0; // screen id...
    $this->open($data);
  }

  public function open($data): void {
    if ($this->window !== null) {
      throw new \LogicException('Window is already open.');
    }
    $flags = SDL::SDL_WINDOW_HIDDEN;
    if ($data['resizable'] ?? true) {
      $flags |= SDL::SDL_WINDOW_RESIZABLE;
    }
    if ($data['state'] === 'fullscreen') {
      $flags |= SDL::SDL_WINDOW_FULLSCREEN;
    } else if ($data['state'] === 'maximized') {
      $flags |= SDL::SDL_WINDOW_MAXIMIZED;
    }
    $this->window = $this->sdl->ffi->SDL_CreateWindow(
      $data['title'],
      $data['width'] * $this->font->cellWidth(),
      $data['height'] * $this->font->cellHeight(),
      $flags
    );
    if ($this->window === null) {
      throw new \RuntimeException('Cannot create window: ' . $sdl->error());
    }
    $this->id = (int)$this->sdl->ffi->SDL_GetWindowID($this->window);
    $this->ffiRenderer = $this->sdl->ffi->SDL_CreateRenderer($this->window, null);
    if ($this->ffiRenderer === null) {
      throw new \RuntimeException('Cannot create renderer: ' . $sdl->error());
    }
    $this->sdl->ffi->SDL_StartTextInput($this->window);
    if ($data['state'] !== 'hidden') {
      $this->show();
    }
    $this->grid = new \SPTK\Rendering\Grid(1, 1);
    $this->resize();
  }

  public function close() {
    
  }

  public function id(): int {
    return $this->id;
  }

  public function show(): void {
    $this->sdl->ffi->SDL_ShowWindow($this->window);
    $this->clear();
    $this->sdl->ffi->SDL_SyncWindow($this->window);
  }

  public function clear() {
    $this->sdl->ffi->SDL_SetRenderDrawColor($this->ffiRenderer, 0, 0, 0, 255);
    $this->sdl->ffi->SDL_RenderClear($this->ffiRenderer);
    $this->sdl->ffi->SDL_RenderPresent($this->ffiRenderer);
  }

  public function resize() {
    $this->clear();
    $this->sdl->ffi->SDL_GetWindowSize($this->window, \FFI::addr($this->ffiWidth), \FFI::addr($this->ffiHeight));
    $this->width = (int)$this->ffiWidth->cdata;
    $this->height = (int)$this->ffiHeight->cdata;
    $this->columns = max(1, intdiv($this->width, 10)) - 1;
    $this->rows = max(1, intdiv($this->height, 20)) - 1;
    $this->grid->resize($this->columns, $this->rows);
    $grid = new \SPTK\Layout\Tile(0, 0, $this->columns, $this->rows);
    $paddings = $this->calculatePaddings();
    foreach ($this->screens as $screen) {
      $screen->measureGrid($grid);
      $screen->measureArea($grid, $paddings);
      $screen->paint();
    }
  }

  private function calculatePaddings(): array {
    $verticalPadding = $this->height - $this->rows * 20;
    $paddingTop = intdiv($verticalPadding, 2);
    $paddingBottom = $verticalPadding - $paddingTop;
    $horizontalPadding = $this->width - $this->columns * 10;
    $paddingLeft = intdiv($horizontalPadding, 2);
    $paddingRight = $horizontalPadding - $paddingLeft;
    return [$paddingTop, $paddingLeft, $paddingBottom, $paddingRight];
  }

  public function handleEvent(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED) {
      \SPTK\App::eventLoop()->quitWindow($this->id);
      return true;
    }
    if (
      $event->type === SDL::SDL_EVENT_WINDOW_RESIZED ||
      $event->type === SDL::SDL_EVENT_WINDOW_MAXIMIZED ||
      $event->type === SDL::SDL_EVENT_WINDOW_RESTORED
    ) {
      $this->resize();
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_WINDOW_EXPOSED) {
      foreach ($this->screens as $screen) {
        $screen->paint();
      }
      return true;
    }
    if (
      $event->type === SDL::SDL_EVENT_TEXT_INPUT ||
      $event->type === SDL::SDL_EVENT_KEY_DOWN ||
      $event->type === SDL::SDL_EVENT_KEY_UP
    ) {
      $this->screens[$this->currentScreen]->handleEvent($event);
      return true;
    }
    return false;
  }

}
