<?php

namespace SPTK\Elements;

use \SPTK\Element;

class Field extends Element {

  private $elementValue;

  protected function init(): void {
    $this->value = '';
    $this->elementValue = new InputValue($this);
  }

  public function getAttributeList(): array {
    return ['value'];
  }

  public function setValue($value): void {
    if ($value === false) {
      $value = '';
    }
    $this->value = (string) $value;
    $this->elementValue->setValue($this->value);
  }

}
