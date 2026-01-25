<?php

namespace SPTK;

class Display extends Element {

  protected $displayId;

  public function setDisplaySize() {
    $sdl = SDL::$instance->sdl;
    $workArea = $sdl->new('SDL_Rect');
    $sdl->SDL_GetDisplayUsableBounds($this->displayId, \FFI::addr($workArea));
    $this->geometry->x = $workArea->x;
    $this->geometry->y = $workArea->y;
    $this->geometry->width = $workArea->w;
    $this->geometry->height = $workArea->h;
    $this->geometry->innerWidth = $this->geometry->width;
    $this->geometry->innerHeight = $this->geometry->height;
    $this->geometry->fullWidth = $this->geometry->width;
    $this->geometry->fullHeight = $this->geometry->height;
  }

  public function setDisplayId($displayId) {
    $this->displayId = $displayId;
  }

  public function getDisplayId() {
    return $this->displayId;
  }

  protected function render() {
    foreach ($this->stack as $descendant) {
      $descendant->render();
    }
    return false;
  }

  protected function measure() {
    $this->geometry->windowWidth = $this->ancestor->geometry->windowWidth;
    $this->geometry->windowHeight = $this->ancestor->geometry->windowHeight;
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
    foreach ($this->descendants as $window) {
      $handled = $window->eventHandler($event);
      if ($handled) {
        return true;
      }
    }
   return false;
  }

  protected function isActive() {
    return true;
  }

  public function findAncestorByType($type) {
    return false;
  }

}
