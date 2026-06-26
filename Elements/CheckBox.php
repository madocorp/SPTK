<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class CheckBox extends Element {

  protected $valueBox;
  protected $onChange = false;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->valueBox = new Element($this, null, null, 'CheckBoxValue');

  }

  public function getAttributeList(): array {
    return ['value', 'onChange'];
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

  public function setOnChange($value) {
    if ($value === false) {
      return;
    }
    if (is_array($value)) {
      $this->onChange = $value;
    } else {
      $this->onChange = self::parseCallback($value);
    }
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      $this->valueBox->addVariant($class);
    }
    parent::addVariant($class);
  }

  public function removeVariant($class): void {
    if ($class == 'active') {
      $this->valueBox->removeVariant($class);
    }
    parent::removeVariant($class);
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
        if ($this->onChange !== false) {
          call_user_func($this->onChange, $this);
        }
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
