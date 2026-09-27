<?php

namespace SPTK\Core;

use SPTK\SDLWrapper\SDL;
use SPTK\Layout\LayoutLeaf;

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
  private $pixelRenderer;
  private $ffiWidth;
  private $ffiHeight;
  private $width;
  private $height;
  private $columns;
  private $rows;
  private $grid;
  private ?\FFI\CData $frameTexture = null;
  private int $offsetX = 0;
  private int $offsetY = 0;

  public function __construct(array $data) {
    $this->ffiWidth = \FFI::new('int');
    $this->ffiHeight = \FFI::new('int');
    $this->sdl = \SPTK\App::sdl();
    $this->font = \SPTK\App::font();
    $this->screens = $data['screens'];
    $this->currentScreen = 0; // screen id...
    foreach ($this->screens as $screen) {
      $screen->setWindow($this);
      $screen->setCurrentScreenId($this->screens[0]->id);
    }
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
    $this->pixelRenderer = new \SPTK\Rendering\PixelRenderer($this->ffiRenderer);
    $this->gridRenderer = new \SPTK\Rendering\GridRenderer($this->ffiRenderer, $this->pixelRenderer);
    $this->sdl->ffi->SDL_StartTextInput($this->window);
    $this->grid = new \SPTK\Rendering\Grid(1, 1);
    $this->resize();
    if ($data['state'] !== 'hidden') {
      $this->show();
    }
  }

  public function close() {
    $this->pixelRenderer?->close();
    $this->gridRenderer?->close();
    if ($this->frameTexture !== null) {
      $this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, null);
      $this->sdl->ffi->SDL_DestroyTexture($this->frameTexture);
      $this->frameTexture = null;
    }
    if ($this->ffiRenderer !== null) {
      $this->sdl->ffi->SDL_DestroyRenderer($this->ffiRenderer);
      $this->ffiRenderer = null;
    }
    if ($this->window !== null) {
      $this->sdl->ffi->SDL_DestroyWindow($this->window);
      $this->window = null;
    }
  }

  public function id(): int {
    return $this->id;
  }

  /** Select a screen by its zero-based XML definition index. */
  public function setCurrentScreen(int $index): void {
    if (!isset($this->screens[$index])) {
      throw new \OutOfBoundsException("Unknown screen index: {$index}");
    }
    if ($index === $this->currentScreen) {
      if ($this->screens[$index]->focusScreenSelectorButton()) {
        $this->renderScreens();
      }
      return;
    }
    $this->screens[$this->currentScreen]->release();
    $this->currentScreen = $index;
    foreach ($this->screens as $screen) {
      $screen->setCurrentScreenId($this->screens[$index]->id);
    }
    $this->renderScreens();
  }

  /** Select a screen by its XML identifier. */
  public function setCurrentScreenId(string $id): void {
    foreach ($this->screens as $index => $screen) {
      if ($screen->id === $id) {
        $this->setCurrentScreen($index);
        return;
      }
    }
    throw new \OutOfBoundsException("Unknown screen id: {$id}");
  }

  /** Return a screen by its XML identifier. */
  public function screen(string $id): ?Screen {
    foreach ($this->screens as $screen) {
      if ($screen->id === $id) {
        return $screen;
      }
    }
    return null;
  }

  public function show(): void {
    $this->sdl->ffi->SDL_ShowWindow($this->window);
    $this->sdl->ffi->SDL_SyncWindow($this->window);
  }

  public function resize() {
    $this->sdl->ffi->SDL_GetWindowSize($this->window, \FFI::addr($this->ffiWidth), \FFI::addr($this->ffiHeight));
    $this->width = (int)$this->ffiWidth->cdata;
    $this->height = (int)$this->ffiHeight->cdata;
    if ($this->frameTexture !== null) {
      $this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, null);
      $this->sdl->ffi->SDL_DestroyTexture($this->frameTexture);
    }
    $this->frameTexture = $this->sdl->ffi->SDL_CreateTexture($this->ffiRenderer, SDL::SDL_PIXELFORMAT_RGBA8888, SDL::SDL_TEXTUREACCESS_TARGET, max(1, $this->width), max(1, $this->height));
    if ($this->frameTexture === null) {
      throw new \RuntimeException('Cannot create window render target: ' . $this->sdl->error());
    }
    $this->columns = max(1, intdiv($this->width, $this->font->cellWidth()) - 2);
    $this->rows = max(1, intdiv($this->height, $this->font->cellHeight()) - 1);
    $offsetX = intdiv($this->width - $this->columns * $this->font->cellWidth(), 2);
    $offsetY = intdiv($this->height - $this->rows * $this->font->cellHeight(), 2);
    $this->offsetX = $offsetX;
    $this->offsetY = $offsetY;
    $this->grid->resize($this->columns, $this->rows);
    $grid = new \SPTK\Layout\Tile(0, 0, $this->columns, $this->rows);
    foreach ($this->screens as $screen) {
      $screen->measureGrid($grid);
      $screen->measureArea($grid, $this->font->cellWidth(), $this->font->cellHeight(), $offsetX, $offsetY, $this->width, $this->height);
    }
    $this->gridRenderer->setOffset($offsetX, $offsetY);
    $this->renderScreens();
  }

  private function renderScreens(): void {
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, $this->frameTexture), 'SDL_SetRenderTarget');
    $this->pixelRenderer->invalidateDrawColor();
    $this->pixelRenderer->setDrawColor(new Color(0, 0, 0));
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_RenderClear($this->ffiRenderer), 'SDL_RenderClear');
    $this->grid->clear();
    $screen = $this->screens[$this->currentScreen];
    $screen->drawBackgrounds($this->pixelRenderer);
    $screen->drawSeparators($this->pixelRenderer);
    $screen->paint($this->grid);
    $this->gridRenderer->draw($this->ffiRenderer, $this->grid);
    $this->pixelRenderer->beginImages();
    $screen->paintPixels($this->pixelRenderer, $this->font->cellWidth(), $this->font->cellHeight(), $this->offsetX, $this->offsetY);
    $this->pixelRenderer->endImages();
    $this->presentFrame();
  }

  /** Repaint one active widget and draw only its changed content. */
  private function renderLeaf(LayoutLeaf $leaf): void {
    $this->grid->beginUpdate();
    $leaf->paintUpdate($this->grid);
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, $this->frameTexture), 'SDL_SetRenderTarget');
    $this->pixelRenderer->invalidateDrawColor();
    if ($leaf->instance()->paintsPixels()) {
      $this->grid->dirtyCells();
      $leaf->drawBackground($this->pixelRenderer);
      $this->gridRenderer->drawTile($this->ffiRenderer, $this->grid, $leaf->grid());
      $leaf->paintPixels($this->pixelRenderer, $this->font->cellWidth(), $this->font->cellHeight(), $this->offsetX, $this->offsetY, true);
      $this->screens[$this->currentScreen]->drawSeparators($this->pixelRenderer);
      $this->presentFrame();
    } else if ($this->gridRenderer->drawDirty($this->ffiRenderer, $this->grid) > 0) {
      $this->presentFrame();
    }
  }

  /** Redraw the old and new focus tiles with their updated selection colors. */
  private function renderFocusChange(LayoutLeaf $before, LayoutLeaf $after): void {
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, $this->frameTexture), 'SDL_SetRenderTarget');
    $this->pixelRenderer->invalidateDrawColor();
    foreach ([[$before, false], [$after, true]] as [$leaf, $selected]) {
      $leaf->drawBackground($this->pixelRenderer, $selected);
      $leaf->paint($this->grid, $selected);
      $this->gridRenderer->drawTile($this->ffiRenderer, $this->grid, $leaf->grid());
      if ($leaf->instance()->paintsPixels()) {
        $leaf->paintPixels($this->pixelRenderer, $this->font->cellWidth(), $this->font->cellHeight(), $this->offsetX, $this->offsetY, $selected);
      }
    }
    $this->screens[$this->currentScreen]->drawSeparators($this->pixelRenderer);
    $this->presentFrame();
  }

  /** Copy the retained frame to the window backbuffer and present it. */
  private function presentFrame(): void {
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetRenderTarget($this->ffiRenderer, null), 'SDL_SetRenderTarget');
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_RenderTexture($this->ffiRenderer, $this->frameTexture, null, null), 'SDL_RenderTexture');
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_RenderPresent($this->ffiRenderer), 'SDL_RenderPresent');
  }

  public function handleEvent(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED) {
      $this->screens[$this->currentScreen]->release();
      \SPTK\App::eventLoop()->quitWindow($this->id);
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_WINDOW_FOCUS_LOST) {
      $this->screens[$this->currentScreen]->release();
      $this->renderScreens();
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
      $this->renderScreens();
      return true;
    }
    if (
      $event->type === SDL::SDL_EVENT_TEXT_INPUT ||
      $event->type === SDL::SDL_EVENT_KEY_DOWN ||
      $event->type === SDL::SDL_EVENT_KEY_UP
    ) {
      $index = $this->currentScreen;
      $screen = $this->screens[$index];
      $leaf = $screen->activeLeaf();
      $selected = $screen->selectedLeaf();
      $handled = $screen->handleEvent($event);
      if ($index !== $this->currentScreen) {
        return true;
      }
      if ($handled && $leaf !== null && $leaf === $screen->activeLeaf()) {
        $this->renderLeaf($leaf);
      } else if ($handled && $selected !== null && $screen->selectedLeaf() !== null && $selected !== $screen->selectedLeaf()) {
        $this->renderFocusChange($selected, $screen->selectedLeaf());
      } else {
        $this->renderScreens();
      }
      return true;
    }
    return false;
  }

}
