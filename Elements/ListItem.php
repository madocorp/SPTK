<?php

namespace SPTK;

class ListItem extends Element {

  protected $selected = false;
  protected $selectable = false;
  protected $filterable = false;
  protected $pre = '';
  protected $after;
  protected $itemLeft;
  protected $itemRight;
  protected $valueField;
  protected $matchField;
  protected $afterMatchField = '';
  private $matched = false;
  private $text = '';

  protected function init() {
    $this->itemLeft = new Element($this, false, false, 'ItemLeft');
    $this->itemRight = new Element($this, false, false, 'ItemRight');
    $this->valueField = new InputValue($this);
    $this->matchField = new InputValue($this);
    $this->matchField->addClass('matched', true);
    $this->afterMatchField = new InputValue($this);
  }

  public function postInit() {
    $text = [];
    foreach ($this->descendants as $descendant) {
      if ($descendant->type === 'Word') {
        $this->removeDescendant($descendant);
        $text[] = $descendant->getValue();
      }
    }
    $this->text = implode(' ', $text);
    if ($this->text !== '') {
      $this->valueField->setValue($this->text);
    }
  }

  public function getAttributeList() {
    return ['value', 'selectable', 'selected', 'filterable', 'left', 'right'];
  }

  public function setValue($value) {
    $this->value = $value;
    $this->text = $value;
    $this->valueField->setValue($this->text);
  }

  public function setSelectable($value) {
    if ($value === true || $value === 'true') {
      $this->selectable = true;
    } else if ($value === 'false') {
      $this->selectable = false;
    } else {
      $this->selectable = $value;
    }
  }

  public function setSelected($value) {
    if ($value === true || $value === 'true') {
      $this->selected = true;
      if ($this->selectable === true) {
        $this->itemLeft->setText('X');
      } else {
        $this->itemLeft->setText('*');
      }
    }
  }

  public function setFilterable($value) {
    $this->filterable = ($value === true || $value === 'true');
  }

  public function setLeft($value) {
    if ($value !== false) {
      $this->itemLeft->setText($value);
    }
  }

  public function setRight($value) {
    if ($value !== false) {
      $this->itemRight->setText($value);
    }
  }

  public function isSelectable() {
    return $this->selectable;
  }

  public function getValue() {
    if ($this->value === false || $this->value === '') {
      return $this->text;
    }
    return $this->value;
  }

  public function deselect() {
    $this->selected = false;
    $this->itemLeft->clear();
  }

  public function select() {
    if ($this->selected && $this->selectable === true) {
      $this->selected = false;
      $this->itemLeft->clear();
    } else {
      $this->selected = true;
      if ($this->selectable === true) {
        $this->itemLeft->setText('X');
      } else {
        $this->itemLeft->setText('*');
      }
    }
  }

  public function match($search) {
    if ($this->filterable === false) {
      return true;
    }
    if ($this->text !== '' && $search !== false) {
      $pos = strpos($this->text, $search);
      if ($pos === 0) {
        $slen = mb_strlen($search);
        $this->matched = true;
        $before = '';
        $match = mb_substr($this->text, $pos, $slen);
        $after = mb_substr($this->text, $pos + $slen);
        $this->valueField->setValue($before);
        $this->matchField->setValue($match);
        $this->afterMatchField->setValue($after);
        return true;
      }
    }
    if ($this->matched) {
      $this->valueField->setValue($this->text);
      $this->matchField->setValue('');
      $this->afterMatchField->setValue('');
    }
    return false;
  }

}
