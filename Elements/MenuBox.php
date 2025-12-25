<?php

namespace SPTK;

class MenuBox extends Element {

  public $belongsTo = false;
  public $submenu = false;
  public $activeMenu = 0;
  protected $num = 0;

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  protected function addDescendant($element) {
    if ($element->type !== 'MenuBoxItem') {
      throw new \Exception("In MenuBox only MenuBoxItem elements are allowed!");
    }
    $this->num++;
    parent::addDescendant($element);
  }

  public function getValue() {
    $selected = [];
    foreach ($this->descendants as $item) {
      if ($item->selectable && $item->selected) {
        $selected[] = $item->name;
      }
    }
    return $selected;
  }

  public function getRadioValue($group) {
    foreach ($this->descendants as $item) {
      if ($item->group === $group && $item->selected) {
        return $item->name;
      }
    }
    return false;
  }

  public function clear() {
    parent::clear();
    $this->num = 0;
    $this->activeMenu = 0;
  }

  public function getItemCount() {
    return $this->num;
  }

  public function getAttributeList() {
    return ['belongsTo', 'submenu'];
  }

  public function setBelongsTo($value) {
    $this->belongsTo = $value;
  }

  public function setSubmenu($value) {
    $this->submenu = ($value === 'true');
  }

  public function activateMenuBoxItem($menu = false) {
    if ($menu === false) {
      $menu = $this->activeMenu;
    }
    if (empty($this->stack)) {
      return;
    }
    $boxItem = end($this->stack);
    $boxItem->removeClass('active', true);
    $i = 0;
    foreach ($this->descendants as $element) {
      if ($i == $menu) {
        $boxItem = $element;
        $boxItem->addClass('active', true);
        $boxItem->raise();
        break;
      }
      $i++;
    }
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->calculateSize();
    $this->geometry->setDerivedSize();
    foreach ($this->descendants as $element) {
      $element->measure();
    }
  }

  protected function calculateSize() {
    $items = self::allByType('MenuBoxItem', $this);
    $width = 0;
    foreach ($items as $item) {
      $iwidth = 0;
      foreach ($item->descendants as $word) {
        $iwidth += $word->geometry->width;
        $iwidth += $word->style->get('wordSpacing');
      }
      if ($iwidth > $width) {
        $width = $iwidth;
      }
    }
    $this->geometry->width = $width;
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::CLOSE:
        if ($this->submenu) {
          $this->hide();
          Element::refresh();
          return true;
        }
        break;
      case Action::MOVE_LEFT:
        if ($this->submenu) {
          $this->lower();
          $this->hide();
          Element::refresh();
        } else {
          $this->ancestor->ancestor->previousMenu();
        }
        return true;
      case Action::SWITCH_PREVIOUS:
        $this->ancestor->ancestor->previousMenu();
        return true;
      case Action::SWITCH_NEXT:
        $this->ancestor->ancestor->nextMenu();
        return true;
      case Action::MOVE_RIGHT:
        if (!$this->submenu) {
          $this->ancestor->ancestor->nextMenu();
          return true;
        }
        break;
      case Action::MOVE_UP:
        $this->activeMenu--;
        if ($this->activeMenu < 0) {
          $this->activeMenu = $this->num - 1;
        }
        $this->activateMenuBoxItem($this->activeMenu);
        Element::immediateRender($this);
        return true;
      case Action::MOVE_DOWN:
        $this->activeMenu++;
        if ($this->activeMenu >= $this->num) {
          $this->activeMenu = 0;
        }
        $this->activateMenuBoxItem($this->activeMenu);
        Element::immediateRender($this);
        return true;
    }
    return false;
  }

}
