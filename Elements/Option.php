<?php

namespace SPTK\Elements;

use \SPTK\Element;

class Option extends Element {

  protected function init(): void {
    $this->display = false;
  }

  public function getAttributeList(): array {
    return ['value'];
  }

  public function postInit(): void {
    if ($this->value === false || $this->value === '') {
      $text = $this->getText();
      if ($text !== '') {
        $this->value = $text;
      }
    }
  }

}
