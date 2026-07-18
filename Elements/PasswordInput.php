<?php

namespace SPTK\Elements;

class PasswordInput extends Input {

  public function __construct(?\SPTK\Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    parent::__construct($ancestor, $name, $class, 'Input');
  }

  protected function displayValue(string $value): string {
    return str_repeat('*', mb_strlen($value));
  }

}
