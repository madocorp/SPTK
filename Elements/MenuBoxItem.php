<?php

namespace SPTK\Elements;

class MenuBoxItem extends ListItem {

  public function getAttributeList(): array {
    return array_merge(parent::getAttributeList(), ['submenu', 'onOpen', 'separator']);
  }

  public function setSubmenu($value): void {
    $row = $this->getBackingRow();
    if ($row instanceof MenuBoxRow) {
      $row->setSubmenu($value);
    }
  }

  public function setOnOpen($value): void {
    $row = $this->getBackingRow();
    if ($row instanceof MenuBoxRow) {
      $row->setOnOpen($value);
    }
  }

  public function setSeparator($value): void {
    $row = $this->getBackingRow();
    if ($row instanceof MenuBoxRow) {
      if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
        $row->addClass('MenuSeparator');
      } else {
        $row->removeClass('MenuSeparator');
      }
    }
  }

  public function isSubmenu(): bool|string {
    $row = $this->getBackingRow();
    return $row instanceof MenuBoxRow ? $row->isSubmenu() : false;
  }

  public function open(): void {
    $row = $this->getBackingRow();
    if ($row instanceof MenuBoxRow) {
      $row->open();
    }
  }

  public function openSubmenu(): bool {
    $row = $this->getBackingRow();
    return $row instanceof MenuBoxRow && $row->openSubmenu();
  }

}
