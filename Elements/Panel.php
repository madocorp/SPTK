<?php

namespace SPTK;

class Panel extends Box {

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function show() {
    $this->display = true;
    $this->raise();
  }

  public function hide() {
    $this->display = false;
    $this->lower();
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    if ($event['key'] == KeyCode::ESCAPE) {
      $this->hide();
      Element::refresh();
      return true;
    }
    return false;
  }

}
