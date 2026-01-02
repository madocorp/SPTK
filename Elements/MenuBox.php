<?php

namespace SPTK;

class MenuBox extends ListBox {

  public $belongsTo = false;
  public $submenu = false;
  protected $num = 0;

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    $attributeList = parent::getAttributeList();
    return array_merge($attributeList, ['belongsTo', 'submenu']);
  }

  public function setBelongsTo($value) {
    $this->belongsTo = $value;
  }

  public function setSubmenu($value) {
    if ($value === true || $value === 'true') {
      $this->submenu = true;
    }
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->calculateWidth();
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
    switch ($a = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
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
        if ($this->descendants[$this->activeItem]->isSubmenu()) {
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
    return parent::keyPressHandler($element, $event);
  }

}
