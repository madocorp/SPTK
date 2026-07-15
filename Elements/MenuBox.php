<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class MenuBox extends ListBox {

  public $belongsTo = false;
  public $submenu = false;
  protected $num = 0;
  protected $jumpToSelected = false;

  protected function init(): void {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addVariant('active');
  }

  public function getAttributeList(): array {
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

  public function hide(): void {
    $this->resetSearch();
    parent::hide();
  }

  protected function measure(): void {
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
    $this->geometry->width = max($this->geometry->minWidth, $width);
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
      case Action::SELECT_ITEM:
        if ($this->descendants[$this->activeItem]->isSubmenu()) {
          return $this->descendants[$this->activeItem]->openSubmenu();
        }
        if (!$this->isActiveItemSelectable()) {
          return true;
        }
        parent::keyPressHandler($element, $event);
        $this->descendants[$this->activeItem]->open();
        $this->raise();
        Element::refresh();
        return true;
      case Action::DO_IT:
        if ($this->descendants[$this->activeItem]->isSubmenu()) {
          return $this->descendants[$this->activeItem]->openSubmenu();
        }
        $menu = $this->findAncestorByType('Menu');
        $menu->closeMenu();
        $selectOnReturn = $this->selectOnReturn;
        $this->selectOnReturn = true;
        parent::keyPressHandler($element, $event);
        $this->selectOnReturn = $selectOnReturn;
        $this->descendants[$this->activeItem]->open();
        return true;
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

  private function isActiveItemSelectable(): bool {
    return isset($this->descendants[$this->activeItem]) &&
      $this->descendants[$this->activeItem]->isSelectable() !== false;
  }

}
