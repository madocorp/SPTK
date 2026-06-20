<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class CheckBox extends Element {

  protected $valueBox;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->valueBox = new Element($this, null, null, 'CheckBoxValue');

  }

  public function getAttributeList(): array {
    return ['value'];
  }

  public function setValue($value): void {
    if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
      $this->value = true;
      $this->valueBox->setText('X');
    } else {
      $this->value = false;
      $this->valueBox->setText('');
    }
  }

  public function addClass($class, $variant = false): void {
    if ($variant && $class == 'active') {
      $this->valueBox->addClass($class, $variant);
    }
    parent::addClass($class, $variant);
  }

  public function removeClass($class, $variant = false): void {
    if ($variant && $class == 'active') {
      $this->valueBox->removeClass($class, $variant);
    }
    parent::removeClass($class, $variant);
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
