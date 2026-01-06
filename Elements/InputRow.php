<?php

namespace SPTK;

class InputRow extends Element {

  protected $y = 0;
  protected $x = 0;

  public function lineBreak() {
    return true;
  }

  public function setPos($row, $lineHeight) {
    $this->y = $row * $lineHeight + $this->ancestor->geometry->paddingTop + $this->ancestor->geometry->borderTop;
    $this->x = $this->ancestor->geometry->paddingLeft + $this->ancestor->geometry->borderLeft;
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->y = $this->y;
    $this->geometry->x = 10; //$this->x;
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }


}