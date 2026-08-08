<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class Menu extends Element {

  protected $bar;
  protected $sub;
  protected array $subs = [];
  protected $openedIndex = false;
  protected string|false $openedName = false;
  protected array|false $onClose = false;

  protected function init(): void {
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function addDescendant($element): void {
    parent::addDescendant($element);
    if ($element->type == 'MenuBar') {
      $this->bar = $element;
    } else if ($element->type == 'SubMenu') {
      if ($this->sub === null) {
        $this->sub = $element;
      }
      $this->subs[] = $element;
    }
  }

  public function getAttributeList(): array {
    return array_merge(parent::getAttributeList(), ['onClose']);
  }

  public function setOnClose($value): void {
    $this->onClose = Element::parseCallback($value);
  }

  public function closeMenu() {
    $closedName = $this->openedName;
    $wasOpen = $this->openedIndex !== false;
    $this->bar->inactivateMenuBarItems();
    $this->openedIndex = false;
    $this->openedName = false;
    foreach ($this->subs as $sub) {
      $sub->closeMenuBoxes();
    }
    if ($wasOpen && $this->onClose !== false) {
      call_user_func($this->onClose, $this, $closedName);
    }
  }

  public function openMenu($menuIndex) {
    if ($this->openedIndex !== false && $this->openedIndex !== $menuIndex) {
      $this->closeMenu();
    }
    $barItem = $this->bar->activateMenuBarItem($menuIndex);
    if ($barItem === false) {
      $this->openedIndex = false;
      $this->openedName = false;
      return false;
    }
    $this->openedIndex = $menuIndex;
    $this->openedName = $barItem->getName();
    foreach ($this->subs as $sub) {
      $sub->showMenuBox($barItem->getName(), $barItem->geometry->x, 0, true);
    }
    return true;
  }

  public function nextMenu() {
    $menuIndex = $this->openedIndex + 1;
    if ($menuIndex >= $this->bar->getItemCount()) {
      $menuIndex = 0;
    }
    $this->openMenu($menuIndex);
  }

  public function previousMenu() {
    $menuIndex = $this->openedIndex - 1;
    if ($menuIndex < 0) {
      $menuIndex = $this->bar->getItemCount() - 1;
    }
    $this->openMenu($menuIndex);
  }

  public function keyPressHandler($element, $event) {
    $menu = false;
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case KeyCode::F1: $menu = 0; break;
      case KeyCode::F2: $menu = 1; break;
      case KeyCode::F3: $menu = 2; break;
      case KeyCode::F4: $menu = 3; break;
      case KeyCode::F5: $menu = 4; break;
      case KeyCode::F6: $menu = 5; break;
      case KeyCode::F7: $menu = 6; break;
      case KeyCode::F8: $menu = 7; break;
      case KeyCode::F9: $menu = 8; break;
      case KeyCode::F10: $menu = 9; break;
      case KeyCode::F11: $menu = 10; break;
      case KeyCode::F12: $menu = 11; break;
      case Action::CLOSE:
        $this->closeMenu();
        return true;
    }
    if ($menu !== false) {
      return $this->openMenu($menu);
    }
    return false;
  }

}
