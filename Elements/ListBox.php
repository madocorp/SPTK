<?php

namespace SPTK;

class ListBox extends Element {

  protected $activeItem = 0;
  protected $num = 0;
  protected $moveable = false;
  protected $multiple = false;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    return ['moveable', 'multiple'];
  }

  public function setMoveable($value) {
    $this->moveable = ($value === 'true');
  }

  public function setMultiple($value) {
    $this->multiple = ($value === 'true');
  }

  public function getValue() {
    $value = [];
    foreach ($this->descendants as $i => $descendant) {
      $key = $descendant->getValue();
      if ($key === false || $key === '') {
        $key = $i;
      }
      $value[$key] = $descendant->getText();
    }
    return $value;
  }

  protected function addDescendant($element) {
    if ($element->type !== 'ListItem') {
      throw new \Exception("In ListBox only ListItem elements are allowed!");
    }
    $this->num++;
    parent::addDescendant($element);
  }

  protected function removeDescendant($element) {
    $this->num--;
    parent::removeDescendant($element);
    if ($this->activeItem >= $this->num) {
      $this->activeItem = $this->num - 1;
    }
    $this->activateItem();
  }

  public function clear() {
    parent::clear();
    $this->activeItem = 0;
    $this->num = 0;
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->addClass('active', true);
        }
      }
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->removeClass('active', true);
        }
      }
    }
    parent::removeClass($class, $dynamic);
  }

  public function setSelected($element) {
    foreach ($this->descendants as $i => $descendant) {
      if ($element->id === $descendant->id) {
        $this->activeItem = $i;
        $this->activateItem();
      }
    }
  }

  public function moveCursor($n) {
    $this->activeItem += $n;
    if ($this->activeItem < 0) {
      $this->activeItem = 0;
    } else if ($this->activeItem >= $this->num) {
      $this->activeItem = $n - 1;
    }
    $this->activateItem();
  }

  public function activateItem() {
    $i = 0;
    foreach ($this->descendants as $descendant) {
      if ($i == $this->activeItem) {
        $descendant->addClass('selected', true);
        $descendant->addClass('active', true);
      } else {
        $descendant->removeClass('selected', true);
        $descendant->removeClass('active', true);
      }
      $i++;
    }
  }

  public function getActive() {
    return $this->descendants[$this->activeItem];
  }

  public function keyPressHandler($element, $event) {
    switch ($event['key']) {
      case KeyCode::UP:
        if ($this->moveable && ($event['mod'] & KeyModifier::SHIFT)) {
          if ($this->activeItem > 0) {
            $item = $this->descendants[$this->activeItem];
            array_splice($this->descendants, $this->activeItem, 1);
            $this->activeItem--;
            array_splice($this->descendants, $this->activeItem, 0, [$item]);
          }
        } else {
          $this->activeItem--;
          if ($this->activeItem < 0) {
            $this->activeItem = $this->num - 1;
          }
          $this->activateItem();
        }
        Element::immediateRender($this);
        return true;
      case KeyCode::DOWN:
        if ($this->moveable && ($event['mod'] & KeyModifier::SHIFT)) {
          if ($this->activeItem < $this->num - 1) {
            $item = $this->descendants[$this->activeItem];
            array_splice($this->descendants, $this->activeItem, 1);
            $this->activeItem++;
            array_splice($this->descendants, $this->activeItem, 0, [$item]);
          }
        } else {
          $this->activeItem++;
          if ($this->activeItem >= $this->num) {
            $this->activeItem = 0;
          }
          $this->activateItem();
        }
        Element::immediateRender($this);
        return true;
    }
    return false;
  }

}
