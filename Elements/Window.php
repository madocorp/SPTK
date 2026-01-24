<?php

namespace SPTK;

class Window extends Element {

  const SDL_RENDERER_ACCELERATED = 0x00000002;
  const SDL_SCALE_MODE_NEAREST= 0;
  const SDL_PIXELFORMAT_RGBA8888 = ((1 << 28) | (6 << 24) | (4 << 20) | (6 << 16) | (32 << 8) | (4 << 0));
  const SDL_WINDOW_FULLSCREEN_DESKTOP = 0x1;
  const SDL_WINDOW_RESIZABLE = 0x20;

  protected $sdl;
  protected $window;
  protected $windowId;
  protected $tmpTexture = false;
  protected $ffiWidth;
  protected $ffiHeight;
  protected $borderTop = 0;
  protected $borderLeft = 0;
  protected $borderBottom = 0;
  protected $borderRight = 0;

  protected function init() {
    $this->display = false;
    $this->sdl = SDL::$instance->sdl;
    $this->window = $this->sdl->SDL_CreateWindow('', 10, 10, self::SDL_WINDOW_RESIZABLE);
    $this->windowId = $this->sdl->SDL_GetWindowID($this->window);
    $this->renderer = $this->sdl->SDL_CreateRenderer($this->window, null);
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, 0, 0, 0, 0xff);
    $this->configure();
    $this->sdl->SDL_StartTextInput($this->window);
    $this->draw();
    $this->display = true;
  }

  public function configure() {
    $this->geometry->x = 0;
    $this->geometry->y = 0;
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->ffiWidth = \FFI::new("int");
    $this->ffiHeight = \FFI::new("int");
    $frameTop = \FFI::new("int");
    $frameBottom = \FFI::new("int");
    $frameLeft = \FFI::new("int");
    $frameRight = \FFI::new("int");
    if ($this->sdl->SDL_GetWindowBordersSize($this->window, \FFI::addr($frameTop), \FFI::addr($frameLeft), \FFI::addr($frameBottom), \FFI::addr($frameRight))) {
      $this->borderTop = $frameTop->cdata;
      $this->borderLeft = $frameLeft->cdata;
      $this->borderBottom = $frameBottom->cdata;
      $this->borderRight = $frameRight->cdata;
      $this->geometry->width = $this->style->get('width', $this->ancestor->geometry);
      $this->geometry->height = $this->style->get('height', $this->ancestor->geometry);
      $this->geometry->x += $frameLeft->cdata;
      $this->geometry->y += $frameTop->cdata;
    } else {
      $this->geometry->width = $this->style->get('width', $this->ancestor->geometry);
      $this->geometry->height = $this->style->get('height', $this->ancestor->geometry);
    }
    $this->setSize();
    $this->getSize();
    $x = $this->style->get('x', $this->ancestor->geometry, $isNegative);
    if ($isNegative) {
      $width = $this->style->get('width', $this->ancestor->geometry);
      $this->geometry->x = $this->ancestor->geometry->x + $this->ancestor->geometry->width - $width - $x;
    } else {
      $this->geometry->x = $x + $this->ancestor->geometry->x + $this->geometry->x;
    }
    $y = $this->style->get('y', $this->ancestor->geometry, $isNegative);
    if ($isNegative) {
      $height = $this->style->get('height', $this->ancestor->geometry);
      $this->geometry->y = $this->ancestor->geometry->y + $this->ancestor->geometry->height - $height - $y;
    } else {
      $this->geometry->y = $this->style->get('y', $this->ancestor->geometry) + $this->ancestor->geometry->y + $this->geometry->y;
    }
    $this->sdl->SDL_SetWindowPosition($this->window, $this->geometry->x, $this->geometry->y);
  }

  public function getAttributeList() {
    return ['title'];
  }

  public function setTitle($title) {
    $this->sdl->SDL_SetWindowTitle($this->window, $title);
  }

  protected function setSize() {
    $this->sdl->SDL_SetWindowSize(
      $this->window,
      $this->geometry->width - $this->borderLeft - $this->borderRight,
      $this->geometry->height - $this->borderTop - $this->borderBottom
    );
    $this->sdl->SDL_SetRenderViewport($this->renderer, null);
  }

  protected function getSize() {
    $this->sdl->SDL_GetWindowSize($this->window, \FFI::addr($this->ffiWidth), \FFI::addr($this->ffiHeight));
    $this->geometry->width = $this->ffiWidth->cdata;
    $this->geometry->height = $this->ffiHeight->cdata;
    $this->geometry->windowWidth = $this->geometry->width;
    $this->geometry->windowHeight = $this->geometry->height;
    $this->geometry->innerWidth = $this->geometry->width - $this->geometry->paddingLeft - $this->geometry->paddingRight;
    $this->geometry->fullWidth = $this->geometry->width;
    $this->geometry->innerHeight = $this->geometry->height - $this->geometry->paddingTop - $this->geometry->paddingBottom;
    $this->geometry->fullHeight = $this->geometry->height;
  }

  public function draw() {
    $color = $this->style->get('backgroundColor');
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $color);
  }

  protected function render() {
    if ($this->texture === false) {
      return false;
    }
    $width = $this->geometry->width - $this->geometry->borderLeft - $this->geometry->borderRight;
    $height = $this->geometry->height - $this->geometry->borderTop - $this->geometry->borderBottom;
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

  public function fullscreenOn() {
    $this->sdl->SDL_SetWindowFullscreen($this->window, true);
  }

  public function fullscreenOff() {
    $this->sdl->SDL_SetWindowFullscreen($this->window, false);
  }

  public function eventHandler($event) {
    if ($this->display === false) {
      return false;
    }
    if (isset($event['windowID']) && $event['windowID'] === $this->windowId) {
      switch ($event['type']) {
        case SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED:
          $this->remove();
          return true;
        case SDL::SDL_EVENT_WINDOW_EXPOSED:
          Element::refresh();
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
