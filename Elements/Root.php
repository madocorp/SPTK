<?php

namespace SPTK;

class Root extends Element {

  protected function init() {
    $sdl = SDL::$instance->sdl;
    $workArea = $sdl->new('SDL_Rect');
    $primaryId = $sdl->SDL_GetPrimaryDisplay();
    $sdl->SDL_GetDisplayUsableBounds($primaryId, \FFI::addr($workArea));
    $this->geometry->x = $workArea->x;
    $this->geometry->y = $workArea->y;
    $this->geometry->width = $workArea->w;
    $this->geometry->height = $workArea->h;
    $this->geometry->innerWidth = $this->geometry->width;
    $this->geometry->innerHeight = $this->geometry->height;
    $this->geometry->fullWidth = $this->geometry->width;
    $this->geometry->fullHeight = $this->geometry->height;
    $this->geometry->windowWidth = $this->geometry->width;
    $this->geometry->windowHeight = $this->geometry->height;
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


  protected function calculateWidths() {
    foreach ($this->descendants as $descendant) {
      $descendant->calculateWidths();
    }
  }

  protected function calculateHeights() {
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
  }

  protected function layout() {
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
  }

  protected function redraw() {
    foreach ($this->descendants as $descendant) {
      $descendant->redraw();
    }
  }

  public function eventHandler($event) {
    $handled = false;
    foreach (self::$root->stack as $display) {
      $handled = $display->eventHandler($event);
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

  public function screenSaver($enabled) {
    $sdl = SDL::$instance->sdl;
    if ($enabled) {
      $sdl->SDL_EnableScreenSaver();
    } else {
      $sdl->SDL_DisableScreenSaver();
    }
  }

}
