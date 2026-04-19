<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class CheckBox extends Element {

  protected $valueBox;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->valueBox = new Element($this, false, false, 'CheckBoxValue');
$this->valueBox->setText('X');
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->valueBox->addClass($class, $dynamic);
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->valueBox->removeClass($class, $dynamic);
    }
    parent::removeClass($class, $dynamic);
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    switch ($keycombo) {
      case KeyCode::SPACE:
echo "toogle checkbox\n";
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
