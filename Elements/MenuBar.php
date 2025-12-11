<?php

namespace SPTK;

class MenuBar extends Element {

  protected $num = 0;

  protected function addDescendant($element) {
    parent::addDescendant($element);
    if ($element->type == 'MenuBarItem') {
      $this->num++;
      $element->setHotKey($this->num);
    }
  }

  public function activateMenuBarItem($menuIndex) {
    $barItem = end($this->stack);
    $barItem->removeClass('MenuBarItem:active');
    $i = 0;
    foreach ($this->descendants as $element) {
      if ($element->type == 'MenuBarItem') {
        if ($i == $menuIndex) {
          $element->addClass('MenuBarItem:active');
          $element->raise();
          $barItem = $element;
          break;
        }
        $i++;
      }
    }
    return $barItem;
  }

  public function inactivateMenuBarItems() {
    foreach ($this->descendants as $element) {
      if ($element->type == 'MenuBarItem') {
        $element->removeClass('MenuBarItem:active');
      }
    }
  }

  public function getItemCount() {
    return $this->num;
  }

}
