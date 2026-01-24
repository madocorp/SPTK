<?php

namespace SPTK;

class MenuBoxItem extends ListItem {

  protected $submenu = false;
  protected $onOpen = false;

  public function getAttributeList() {
    $attributeList = parent::getAttributeList();
    return array_merge($attributeList, ['submenu', 'onOpen']);
  }

  public function setSubmenu($value) {
    if ($value === true || $value === 'true') {
      $this->setRight('>');
      $this->submenu = true;
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
      call_user_func($this->onOpen);
    }
  }

  public function getWidth() {
    $width = $this->valueField->getWidth();
    $width += $this->matchField->getWidth();
    $width += $this->afterMatchField->getWidth();
    return $width + 30;
  }

  public function openSubmenu() {
    $submenu = $this->findAncestorByType('SubMenu');
    foreach ($submenu->descendants as $menuBox) {
      if ($menuBox->belongsTo == $this->name) {
        self::getRelativePos($submenu->id, $this, $x, $y);
        $x += $this->geometry->width;
        $y += floor($this->geometry->height / 2) - $menuBox->geometry->marginTop - $menuBox->geometry->borderTop;
        $submenu->showMenuBox($this->name, $x, $y, false);
        return true;
      }
    }
    return false;
  }

}
