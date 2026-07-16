<?php

namespace SPTK\Elements;

use \SPTK\Element;

class MenuBoxRow extends ListBoxRow {

  protected bool|string $submenu = false;
  protected array|false $onOpen = false;

  public function apply(array $data): void {
    parent::apply($data);
    foreach ($data as $key => $value) {
      switch ($key) {
        case 'submenu': $this->setSubmenu($value); break;
        case 'onOpen': $this->setOnOpen($value); break;
      }
    }
  }

  public function setSubmenu($value): void {
    if ($value === true || $value === 'true') {
      $this->setRight('>');
      $this->submenu = true;
    } else if ($value !== false && $value !== 'false' && $value !== '') {
      $this->setRight('>');
      $this->submenu = (string)$value;
    } else {
      $this->submenu = false;
      $this->setRight('');
    }
  }

  public function isSubmenu(): bool|string {
    return $this->submenu;
  }

  public function setOnOpen($value): void {
    $this->onOpen = Element::parseCallback($value);
  }

  public function open(): void {
    if ($this->onOpen !== false) {
      call_user_func($this->onOpen, $this);
    }
  }

  public function openSubmenu(): bool {
    $list = $this->findAncestorByType('MenuBox');
    if ($list instanceof MenuBox) {
      return $list->openSubmenuForRow($this);
    }
    return false;
  }

  public function getType(): string {
    return 'MenuBoxItem';
  }

}
