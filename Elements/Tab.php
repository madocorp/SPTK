<?php

namespace SPTK\Elements;

use \SPTK\Element;

class Tab extends Element {

  protected $contentName = false;

  public function getAttributeList(): array {
    return ['contentName'];
  }

  public function setContentName($contentName): void {
    $this->contentName = $contentName;
  }

  public function getContentName(): string|false {
    return $this->contentName;
  }

}
