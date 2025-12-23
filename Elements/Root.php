<?php

namespace SPTK;

class Root extends Element {

  protected function init() {
    $sdl = SDL::$instance->sdl;
    $displayId = $sdl->SDL_GetPrimaryDisplay();
    $workArea = $sdl->new('SDL_Rect');
    $sdl->SDL_GetDisplayUsableBounds($displayId, \FFI::addr($workArea));
    $this->geometry->x = $workArea->x;
    $this->geometry->y = $workArea->y;
    $this->geometry->width = $workArea->w;
    $this->geometry->height = $workArea->h;
    $this->geometry->innerWidth = $this->geometry->width;
    $this->geometry->innerHeight = $this->geometry->height;
    $this->geometry->fullWidth = $this->geometry->width;
    $this->geometry->fullHeight = $this->geometry->height;
  }

  protected function render() {
    foreach ($this->stack as $descendant) {
      $descendant->render();
    }
    return false;
  }

  protected function measure() {
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function layout() {
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
  }

  public function eventHandler($event) {
    $handled = false;
    foreach (self::$root->stack as $window) {
      $handled = $window->eventHandler($event);
      if ($handled) {
        break;
      }
    }
    if ($event['type'] == SDL::SDL_QUIT) {
      SDL::$instance->end();
    }
  }

  protected function isActive() {
    return true;
  }

  public function findAncestorByType($type) {
    return false;
  }

}
