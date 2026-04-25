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

  }

  public function getAttributeList() {
    return ['value'];
  }

  public function setValue($value) {
    if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
      $this->value = true;
      $this->valueBox->setText('X');
    } else {
      $this->value = false;
      $this->valueBox->setText('');
    }
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
      case Action::SELECT_ITEM:
        if ($this->value === true) {
          $this->setValue(false);
        } else {
          $this->setValue(true);
        }
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
