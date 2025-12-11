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

  protected function init() {
    $this->sdl = SDL::$instance->sdl;
    $this->window = $this->sdl->SDL_CreateWindow('', 10, 10, self::SDL_WINDOW_RESIZABLE);
    $this->renderer = $this->sdl->SDL_CreateRenderer($this->window, null);
echo "CreateRenderer\n";
    $this->sdl->SDL_SetRenderDrawColor($this->renderer, 0, 0, 0, 0xff);
    $frameTop = \FFI::new("int");
    $frameBottom = \FFI::new("int");
    $frameLeft = \FFI::new("int");
    $frameRight = \FFI::new("int");
    if ($this->sdl->SDL_GetWindowBordersSize($this->window, \FFI::addr($frameTop), \FFI::addr($frameLeft), \FFI::addr($frameBottom), \FFI::addr($frameRight))) {
      $this->geometry->width = $this->style->get('width', $this->ancestor->geometry->width - $frameLeft->cdata - $frameRight->cdata);
      $this->geometry->height = $this->style->get('height', $this->ancestor->geometry->height - $frameTop->cdata - $frameBottom->cdata);
      $this->geometry->x += $frameLeft->cdata;
      $this->geometry->y += $frameTop->cdata;
    } else {
      $this->geometry->width = $this->style->get('width', $this->ancestor->geometry->width);
      $this->geometry->height = $this->style->get('height', $this->ancestor->geometry->height);
    }
    $fontSize = $this->style->get('fontSize', $this->geometry->height);
    $this->calculateGeometry();
    $this->setSize();
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->setCalculatedSize();
    $this->geometry->x = $this->style->get('x', $this->ancestor->geometry->width) + $this->ancestor->geometry->x + $this->geometry->x;
    $this->geometry->y = $this->style->get('y', $this->ancestor->geometry->height) + $this->ancestor->geometry->y + $this->geometry->y;
    $this->sdl->SDL_SetWindowPosition($this->window, $this->geometry->x, $this->geometry->y);
    $this->draw();
  }

  public function setTitle($title) {
    $this->sdl->SDL_SetWindowTitle($this->window, $title);
  }

  protected function setSize() {
    $this->sdl->SDL_SetWindowSize($this->window, $this->geometry->width, $this->geometry->height);
    $this->sdl->SDL_SetRenderViewport($this->renderer, null);
  }

  protected function getSize() {
    $width = \FFI::new("int");
    $height = \FFI::new("int");
    $this->sdl->SDL_GetWindowSize($this->window, \FFI::addr($width), \FFI::addr($height));
    $this->geometry->width = $width->cdata;
    $this->geometry->height = $height->cdata;
    $this->setSize();
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->setCalculatedSize();
  }

  public function draw() {
    $color = $this->style->get('backgroundColor');
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $color);
  }

  protected function render($ptmp) {
    if ($this->texture === false) {
      return false;
    }
    $width = $this->geometry->width - $this->geometry->borderLeft - $this->geometry->borderRight;
    $height = $this->geometry->height - $this->geometry->borderTop - $this->geometry->borderBottom;
    $tmpTexture = new Texture($this->renderer, $width, $height, [0, 0, 0, 0]);
    $this->texture->copyTo($tmpTexture, 0, 0);
    $n = count($this->stack);
    for ($i = 0; $i < $n; $i++) {
      $descendant = $this->stack[$i];
      $dTexture = $descendant->render($tmpTexture);
      if ($dTexture !== false) {
        $dTexture->copyTo($tmpTexture, $descendant->geometry->x, $descendant->geometry->y);
      }
    }
    $tmpTexture->copyTo(null, 0, 0);
    $this->sdl->SDL_RenderPresent($this->renderer);
    return false;
  }

  public function eventHandler($event) {
    if (!$this->display) {
      return false;
    }
    if (true) { // check window id
      if ($event['type'] == SDL::SDL_EVENT_WINDOW_RESIZED) {
        $this->getSize();
        $this->draw();
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

  protected function calculateGeometry() {
    $this->cursor->reset();
    $fontSize = $this->style->get('fontSize', $this->geometry->innerHeight);
    foreach ($this->descendants as $element) {
      $element->calculateGeometry();
    }
    $this->geometry->formatRow($this->cursor, $this->geometry);
  }

  public function getAttributeList() {
    return ['title'];
  }

  public function raise() {
    return;
  }

  public function lower() {
    return;
  }

}
