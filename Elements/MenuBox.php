<?php

namespace SPTK;

class MenuBox extends ListBox {

  public $belongsTo = false;
  public $submenu = false;
  protected $num = 0;
  protected $jumpToSelected = false;

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    $attributeList = parent::getAttributeList();
    return array_merge($attributeList, ['belongsTo', 'submenu', 'jumpToSelected']);
  }

  public function setBelongsTo($value) {
    $this->belongsTo = $value;
  }

  public function setSubmenu($value) {
    if ($value === true || $value === 'true') {
      $this->submenu = true;
    }
  }

  public function setJumpToSelected($value) {
    if ($value === true || $value === 'true') {
      $this->jumpToSelected = true;
    }
  }

  public function gotoSelected() {
    if (!$this->jumpToSelected) {
      return;
    }
    foreach ($this->descendants as $i => $descendant) {
      if ($descendant->isSelected()) {
        $this->activeItem = $i;
        return;
      }
    }
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    if ($this->geometry->width === 'calculated') {
      $this->calculateWidth();
    }
    $this->geometry->setDerivedWidths();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateWidth() {
    $width = 0;
    foreach ($this->descendants as $descendant) {
      $dwidth = $descendant->getWidth();
      if ($dwidth > $width) {
        $width = $dwidth;
      }
    }
    $this->geometry->width = $width + 30; // ...
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    $keyCombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    switch ($keyCombo) {
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
          $menu = $this->findAncestorByType('Menu');
          $menu->previousMenu();
        }
        return true;
      case Action::SWITCH_PREVIOUS:
        $menu = $this->findAncestorByType('Menu');
        $menu->previousMenu();
        return true;
      case Action::SWITCH_NEXT:
        $menu = $this->findAncestorByType('Menu');
        $menu->nextMenu();
        return true;
      case Action::MOVE_RIGHT:
        if (!empty($this->descendants) && $this->descendants[$this->activeItem]->isSubmenu()) {
          return $this->descendants[$this->activeItem]->openSubmenu();
        } else {
          $menu = $this->findAncestorByType('Menu');
          $menu->nextMenu();
          return true;
        }
        break;
      case Action::DO_IT:
        parent::keyPressHandler($element, $event);
        if ($this->descendants[$this->activeItem]->isSubmenu()) {
          return $this->openSubmenu();
        }
        $menu = $this->findAncestorByType('Menu');
        $menu->closeMenu();
        $this->descendants[$this->activeItem]->open();
        return true;
      case Action::MOVE_RIGHT:
        if ($this->descendants[$this->activeItem]->isSubmenu()) {
          return $this->openSubmenu();
        }
        break;
    }
    $handled = parent::keyPressHandler($element, $event);
    if (!$handled) {
      if (in_array($keyCombo, [
        KeyCode::F1, KeyCode::F2, KeyCode::F3, KeyCode::F4,
        KeyCode::F5, KeyCode::F6, KeyCode::F7, KeyCode::F8,
        KeyCode::F9, KeyCode::F10, KeyCode::F11, KeyCode::F12,
        Action::CLOSE
      ])) {
        return false;
      }
    }
    return true;
  }

}
