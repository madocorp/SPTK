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
    parent::addDescendant($element);
    if ($element->type == 'MenuBoxItem') {
      $this->num++;
    }
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
      if ($element->type == 'MenuBoxItem') {
        if ($i == $menu) {
          $boxItem = $element;
          $boxItem->addClass('active', true);
          $boxItem->raise();
          break;
        }
        $i++;
      }
    }
  }

  public function calculateGeometry() {
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
    parent::calculateGeometry();
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    if ($event['key'] == KeyCode::ESCAPE && $this->submenu) {
      $this->hide();
      Element::refresh();
      return true;
    }
    if ($event['key'] == KeyCode::LEFT) {
      if ($this->submenu) {
        $this->lower();
        $this->hide();
        Element::refresh();
      } else {
        $this->ancestor->ancestor->previousMenu();
      }
      return true;
    }
    if ($event['key'] == KeyCode::TAB || ($event['key'] == KeyCode::RIGHT && !$this->submenu)) {
      $this->ancestor->ancestor->nextMenu();
      return true;
    }
    if ($event['key'] == KeyCode::UP) {
      $this->activeMenu--;
      if ($this->activeMenu < 0) {
        $this->activeMenu = $this->num - 1;
      }
      $this->activateMenuBoxItem($this->activeMenu);
      Element::immediateRender($this);
      return true;
    }
    if ($event['key'] == KeyCode::DOWN) {
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
