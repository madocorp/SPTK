<?php

namespace SPTK\Elements;

use \SPTK\Element;

class MenuBar extends Element {

  protected $num = 0;

  protected function addDescendant($element) {
    if ($element->type !== 'MenuBarItem') {
      throw new \Exception("In MenuBar only MenuBarItem elements are allowed!");
    }
    $this->num++;
    $element->setHotKey($this->num);
    parent::addDescendant($element);
  }

  public function activateMenuBarItem($menuIndex) {
    $this->inactivateMenuBarItems();
    $barItem = false;
    $i = 0;
    foreach ($this->descendants as $element) {
      if ($i == $menuIndex) {
        $element->addClass('active', true);
        $element->raise();
        $barItem = $element;
        break;
      }
      $i++;
    }
    return $barItem;
  }

  public function inactivateMenuBarItems() {
    foreach ($this->descendants as $element) {
      $element->removeClass('active', true);
    }
  }

  public function getItemCount() {
    return $this->num;
  }

}
