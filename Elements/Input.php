<?php

namespace SPTK;

class Input extends Element {

  private $word;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->word = new Word($this);
    $this->word->setValue('');
  }

  public function keyPressHandler($element, $event) {
    if ($event['key'] == KeyCode::SPACE) {
      $this->word->setValue('string' . rand(0, 100));
Element::immediateRender($this);
      return true;
    }
    return false;
  }

}
