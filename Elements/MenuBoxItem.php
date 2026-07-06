<?php

namespace SPTK\Elements;

use \SPTK\Element;

class MenuBoxItem extends ListItem {

  protected $submenu = false;
  protected $onOpen = false;

  public function getAttributeList(): array {
    $attributeList = parent::getAttributeList();
    return array_merge($attributeList, ['submenu', 'onOpen']);
  }

  public function setSubmenu($value) {
    if ($value === true || $value === 'true') {
      $this->setRight('>');
      $this->submenu = true;
    } else if ($value !== false && $value !== 'false' && $value !== '') {
      $this->setRight('>');
      $this->submenu = $value;
    }
  }

  public function setOnOpen($value) {
    $this->onOpen = self::parseCallback($value);
  }

  public function isSubmenu() {
    return $this->submenu;
  }

  public function open() {
    if ($this->onOpen !== false) {
      call_user_func($this->onOpen, $this);
    }
  }

  public function openSubmenu() {
    $submenu = $this->findAncestorByType('SubMenu');
    $target = $this->submenu === true ? $this->name : $this->submenu;
    foreach ($submenu->descendants as $menuBox) {
      if ($menuBox->belongsTo == $target) {
        $this->open();
        self::getRenderedRelativePos($submenu->id, $this, $x, $y);
        $x += $this->geometry->width;
        $y += floor($this->geometry->height / 2) - $menuBox->geometry->marginTop - $menuBox->geometry->borderTop;
        $submenu->showMenuBox($target, $x, $y, false);
        return true;
      }
    }
    return false;
  }

}
