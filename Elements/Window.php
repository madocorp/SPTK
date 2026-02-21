<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Texture;
use \SPTK\SDLWrapper\SDL;

class Window extends Element {

  const SDL_RENDERER_ACCELERATED = 0x00000002;
  const SDL_SCALE_MODE_NEAREST = 0;
  const SDL_PIXELFORMAT_RGBA8888 = ((1 << 28) | (6 << 24) | (4 << 20) | (6 << 16) | (32 << 8) | (4 << 0));
  const SDL_WINDOW_RESIZABLE = 0x20;
  const SDL_WINDOW_HIDDEN = 0x8;
  const SDL_WINDOW_MAXIMIZED = 0x80;
  const SDL_WINDOW_FULLSCREEN = 0x01;

  protected $sdl;
  protected $window;
  protected $windowId;
  protected $tmpTexture = false;
  protected $ffiWidth;
  protected $ffiHeight;

  protected function init() {
    $this->display = false;
    $this->sdl = SDL::$instance->sdl;
    $this->ffiWidth = \FFI::new("int");
    $this->ffiHeight = \FFI::new("int");
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $width = $this->style->get('width', $this->ancestor->geometry);
    $height = $this->style->get('height', $this->ancestor->geometry);
    $maximized = 0;
    if ($width === 'max' || $height === 'max') {
      $maximized = self::SDL_WINDOW_MAXIMIZED;
      $this->geometry->windowWidth = $this->ancestor->geometry->width;
      $this->geometry->windowHeight = $this->ancestor->geometry->height;
    } else {
      $this->geometry->windowWidth = $width;
      $this->geometry->windowHeight = $height;
    }
    $this->setDerivedSizes();
    $this->window = $this->sdl->SDL_CreateWindow('', $this->geometry->windowWidth, $this->geometry->windowHeight, self::SDL_WINDOW_RESIZABLE | self::SDL_WINDOW_HIDDEN | $maximized);
    $this->windowId = $this->sdl->SDL_GetWindowID($this->window);
    $this->renderer = $this->sdl->SDL_CreateRenderer($this->window, null);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, 0, 0, 0, 0xff);
    $this->sdl->SDL_ShowWindow($this->window);
    $this->sdl->SDL_StartTextInput($this->window);
    $this->changed = true;
    $this->display = true;
  }

  public function getAttributeList() {
    return ['title'];
  }

  public function setTitle($title) {
    $this->sdl->SDL_SetWindowTitle($this->window, $title);
  }

  public function setSize() {
    $width = $this->style->get('width', $this->ancestor->geometry);
    $height = $this->style->get('height', $this->ancestor->geometry);
    if ($width === 'max' || $height === 'max') {
      $this->maximize();
      $this->geometry->windowWidth = $this->ancestor->geometry->width;
      $this->geometry->windowHeight = $this->ancestor->geometry->height;
    } else {
      $this->restore();
      $this->geometry->windowWidth = $width;
      $this->geometry->windowHeight = $height;
      $this->sdl->SDL_SetWindowSize(
        $this->window,
        $this->geometry->windowWidth,
        $this->geometry->windowHeight
      );
      $this->sdl->SDL_SetRenderViewport($this->renderer, null);
      $this->setDerivedSizes();
    }
  }

  protected function getSize() {
    $this->sdl->SDL_GetWindowSize($this->window, \FFI::addr($this->ffiWidth), \FFI::addr($this->ffiHeight));
    $this->geometry->windowWidth = $this->ffiWidth->cdata;
    $this->geometry->windowHeight =  $this->ffiHeight->cdata;
    $this->setDerivedSizes();
  }

  protected function setDerivedSizes() {
    $this->geometry->width = $this->geometry->windowWidth;
    $this->geometry->height =  $this->geometry->windowHeight;
    $this->geometry->innerWidth = $this->geometry->windowWidth - $this->geometry->paddingLeft - $this->geometry->paddingRight;
    $this->geometry->innerHeight = $this->geometry->windowHeight - $this->geometry->paddingTop - $this->geometry->paddingBottom;
    $this->geometry->fullWidth = $this->geometry->windowWidth;
    $this->geometry->fullHeight = $this->geometry->windowHeight;
  }


  public function draw() {
    $color = $this->style->get('backgroundColor');
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $color);
  }

  protected function render() {
    if ($this->texture === false) {
      return false;
    }
    $width = $this->geometry->width;
    $height = $this->geometry->height;
    if ($this->tmpTexture === false) {
      $this->tmpTexture = new Texture($this->renderer, $width, $height, [0, 0, 0, 0]);
    }
    $this->texture->copyTo($this->tmpTexture, 0, 0);
    $n = count($this->stack);
    for ($i = 0; $i < $n; $i++) {
      $descendant = $this->stack[$i];
      $dTexture = $descendant->render();
      if ($dTexture !== false) {
        $dTexture->copyTo($this->tmpTexture, $descendant->geometry->x, $descendant->geometry->y);
      }
    }
    $this->tmpTexture->copyTo(null, 0, 0);
    $this->sdl->SDL_RenderPresent($this->renderer);
    return false;
  }

  protected function measure() {
    $this->geometry->windowWidth = $this->geometry->width;
    $this->geometry->windowHeight = $this->geometry->height;
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function redraw() {
    foreach ($this->descendants as $descendant) {
      $descendant->redraw();
    }
  }

  public function raise() {
    return;
  }

  public function lower() {
    return;
  }

  public function show() {
    $this->sdl->SDL_RestoreWindow($this->window);
    $this->display = true;
  }

  public function hide() {
    $this->sdl->SDL_MinimizeWindow($this->window);
    $this->display = false;
  }

  public function remove() {
    parent::remove();
    $this->sdl->SDL_DestroyWindow($this->window);
  }

  public function maximize() {
    $flags = $this->sdl->SDL_GetWindowFlags($this->window);
    if ($flags & self::SDL_WINDOW_MAXIMIZED) {
      return;
    }
    $this->sdl->SDL_MaximizeWindow($this->window);
    $this->sdl->SDL_SyncWindow($this->window);
  }

  public function restore() {
    $flags = $this->sdl->SDL_GetWindowFlags($this->window);
    if ($flags & self::SDL_WINDOW_MAXIMIZED) {
      $this->sdl->SDL_RestoreWindow($this->window);
      $this->sdl->SDL_SyncWindow($this->window);
    }
  }

  public function fullscreenOn() {
    $flags = $this->sdl->SDL_GetWindowFlags($this->window);
    if ($flags & self::SDL_WINDOW_FULLSCREEN) {
      return;
    }
    $this->sdl->SDL_SetWindowFullscreen($this->window, true);
    $this->sdl->SDL_SyncWindow($this->window);
  }

  public function fullscreenOff() {
    $flags = $this->sdl->SDL_GetWindowFlags($this->window);
    if ($flags & self::SDL_WINDOW_FULLSCREEN) {
      $this->sdl->SDL_SetWindowFullscreen($this->window, false);
      $this->sdl->SDL_SyncWindow($this->window);
    }
  }

  public function eventHandler($event) {
    if ($this->display === false) {
      return false;
    }
    if (isset($event['windowID']) && $event['windowID'] === $this->windowId) {
      switch ($event['type']) {
        case SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED:
          $this->tmpTexture = false;
          $this->remove();
          return true;
        case SDL::SDL_EVENT_WINDOW_EXPOSED:
          $this->tmpTexture = false;
          $this->getSize();
          $this->draw();
          Element::refresh();
          // DEBUG:5 $this->debug();
          return true;
        case SDL::SDL_EVENT_WINDOW_MAXIMIZED:
        case SDL::SDL_EVENT_WINDOW_RESTORED:
        case SDL::SDL_EVENT_WINDOW_RESIZED:
          $this->tmpTexture = false;
          $this->getSize();
          $this->draw();
          Element::refresh();
          return true;
        case SDL::SDL_EVENT_KEY_DOWN:
        case SDL::SDL_EVENT_KEY_UP:
        case SDL::SDL_EVENT_TEXT_INPUT:
          // send these to the elements
          break;
        default:
          // skip other events
          return true;
      }
      $n = count($this->stack);
      if ($n > 0) {
        for ($i = 0; $i < $n; $i++) {
          $descendant = $this->stack[($n + $i - 1) % $n];
          if ($descendant->display) {
            if ($descendant->eventHandler($event)) {
              return true;
            }
            break;
          }
        }
      }
      if (isset($event['name']) && isset($this->events[$event['name']])) {
        return call_user_func($this->events[$event['name']], $this, $event);
      }
      return false;
    }
    return false;
  }

}
