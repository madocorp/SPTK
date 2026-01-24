<?php

namespace SPTK;

class Root extends Element {

  protected $primaryIndex = 0;

  protected function init() {
    $this->resetDisplays();
  }

  protected function resetDisplays() {
    $currentDisplays = $this->descendants;
    $this->clear();
    $sdl = SDL::$instance->sdl;
    $displayCount = $sdl->new('int');
    $displays = $sdl->SDL_GetDisplays(\FFI::addr($displayCount));
    $primaryId = $sdl->SDL_GetPrimaryDisplay();
    for ($i = 0; $i < $displayCount->cdata; $i++) {
      $displayId = $displays[$i];
      $displayIndex = $this->searchDisplay($currentDisplays, $displayId);
      if ($displayIndex === false) {
        $element = new Display($this);
        $element->setDisplayId($displayId);
        $element->setDisplaySize();
      } else {
        $this->descendants[] = $currentDisplays[$displayIndex];
        $this->stack[] = $currentDisplays[$displayIndex];
        unset($currentDisplays[$displayIndex]);
      }
      if ($displayId === $primaryId) {
        $this->primaryIndex = $i;
      }
    }
    foreach ($currentDisplays as $i => $display) {
      // move all windows on it to primary
    }
  }

  protected function searchDisplay($displays, $id) {
    foreach ($displays as $i => $display) {
      if ($display->getDisplayId() === $id) {
        return $i;
      }
    }
    return false;
  }

  public function getPrimaryDisplay() {
    return $this->descendants[$this->primaryIndex];
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
    switch ($event['type']) {
      case SDL::SDL_EVENT_DISPLAY_ORIENTATION:
      case SDL::SDL_EVENT_DISPLAY_ADDED:
      case SDL::SDL_EVENT_DISPLAY_REMOVED:
      case SDL::SDL_EVENT_DISPLAY_MOVED:
        $this->resetDisplays();
        return true;
    }
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
