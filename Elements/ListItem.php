<?php

namespace SPTK;

class ListItem extends Element {

  protected $selected;
  private $matched = false;

  public function getAttributeList() {
    return ['selected', 'value'];
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

  public function getValue() {
    if ($this->value === false || $this->value === '') {
      return $this->getText();
    }
    return $this->value;
  }

  public function match($search) {
    if ($this->value !== false && $search !== false) {
      $pos = strpos($this->value, $search);
      if ($pos === 0) {
        $slen = mb_strlen($search);
        $this->matched = true;
        $this->clear();
        $before = '';
        $match = mb_substr($this->value, $pos, $slen);
        $after = mb_substr($this->value, $pos + $slen);
        $iv = new InputValue($this);
        $iv->setValue($before);
        $iv = new InputValue($this);
        $iv->setValue($match);
        $iv->addClass('matched', true);
        $iv = new InputValue($this);
        $iv->setValue($after);
        return true;
      }
    }
    if ($this->matched) {
      $this->clear();
      $this->addText($this->value);
    }
    return false;
  }

}
