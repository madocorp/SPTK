<?php

namespace SPTK;

class ConfirmationCode extends Element {

  private $code;
  private $elementCode;
  private $elementSelected;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementCode = new Word($this, false, false, 'InputValue');
    $this->elementCode->setValue('');
    $this->elementSelected = new Word($this, false, false, 'InputValue');
    $this->elementSelected->setValue(' ');
  }

  public function setCode($code) {
    $this->code = $code;
  }

  public function getValue() {
    return ($this->elementCode->getValue() === $this->code);
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->addClass('selected', true);
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->removeClass('selected', true);
    }
    parent::removeClass($class, $dynamic);
  }

  public function keyPressHandler($element, $event) {
    if ($event['key'] == KeyCode::BACKSPACE) {
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
