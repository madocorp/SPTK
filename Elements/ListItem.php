<?php

namespace SPTK;

class ListItem extends Element {

  protected $selected;

  public function getAttributeList() {
    return ['selected'];
  }

  public function setSelected($value) {
    if ($value === true || $value === 'true') {
      $this->selected = true;
      $this->addClass('selected', true);
      $this->ancestor->setSelected($this);
    } else {
      $this->selected = false;
    }
  }

}
