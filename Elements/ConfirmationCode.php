<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class ConfirmationCode extends Element {

  private $code;
  private $elementCode;
  private $elementSelected;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementCode = new InputValue($this);
    $this->elementCode->setValue('');
    $this->elementSelected = new InputValue($this);
    $this->elementSelected->setValue(' ');
  }

  public function setCode($code) {
    $this->code = $code;
  }

  public function getValue(): mixed {
    return ($this->elementCode->getValue() === $this->code);
  }

  public function addClass($class, $variant = false): void {
    if ($variant && $class == 'active') {
      $this->elementSelected->addVariant('selected');
    }
    parent::addClass($class, $variant);
  }

  public function removeClass($class, $variant = false): void {
    if ($variant && $class == 'active') {
      $this->elementSelected->removeVariant('selected');
    }
    parent::removeClass($class, $variant);
  }

  public function keyPressHandler($element, $event) {
    $action = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    if ($action === Action::DELETE_BACK) {
      $code = $this->elementCode->getValue();
      $code = mb_substr($code, 0, -1);
      $this->elementCode->setValue($code);
      Element::immediateRender($this);
      return true;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
    $code = $this->elementCode->getValue();
    if (mb_strlen($code) < 3 && preg_match('/^[0-9]$/', $event['text'])) {
      $code .= $event['text'];
      $this->elementCode->setValue($code);
      Element::immediateRender($this);
    }
    return true;
  }

}
