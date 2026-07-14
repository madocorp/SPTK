<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;
use \SPTK\SDLWrapper\SDL;

class RadioButton extends Element {

  protected $valueBox;
  protected $onChange = false;
  protected $group = false;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->valueBox = new Element($this, null, null, 'RadioButtonValue');
  }

  public function getAttributeList(): array {
    return ['group', 'value', 'onChange'];
  }

  public function setGroup($value): void {
    $this->group = $value === false ? false : (string) $value;
  }

  public function getGroup(): string|false {
    return $this->group;
  }

  public function setValue($value): void {
    if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
      $this->value = true;
      $this->valueBox->setText('O');
      $this->clearGroup();
    } else {
      $this->value = false;
      $this->valueBox->setText('');
    }
  }

  private function clearGroup(): void {
    if ($this->group === false || $this->group === '') {
      return;
    }
    $root = Element::$root;
    if ($root === null) {
      return;
    }
    foreach (Element::allByType('RadioButton', $root) as $radioButton) {
      if ($radioButton === $this || $radioButton->getGroup() !== $this->group) {
        continue;
      }
      $radioButton->value = false;
      $radioButton->valueBox->setText('');
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
        if (SDL::$instance !== null) {
          SDL::$instance->supressTextInput();
        }
        if ($this->value !== true) {
          $this->setValue(true);
          if ($this->onChange !== false) {
            call_user_func($this->onChange, $this);
          }
        }
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
