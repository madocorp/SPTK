<?php

namespace SPTK\Elements;

use \SPTK\Element;

class ListItem extends Element {

  protected ?ListBoxRow $row = null;
  protected string $pendingText = '';

  protected function init(): void {
    $this->display = false;
    if ($this->ancestor instanceof ListBox) {
      $this->ancestor->registerItemElement($this);
    }
  }

  public function getBackingRow(): ?ListBoxRow {
    return $this->row;
  }

  public function setBackingRow(ListBoxRow $row): void {
    $this->row = $row;
  }

  public function postInit(): void {
    $text = [];
    foreach ($this->descendants as $descendant) {
      if ($descendant->getType() === 'Word') {
        $text[] = $descendant->getValue();
      }
    }
    if (!empty($text)) {
      $this->setText(implode(' ', $text));
    } else if ($this->pendingText !== '') {
      $this->setText($this->pendingText);
    }
  }

  public function getAttributeList(): array {
    return ['value', 'selectable', 'selected', 'filterable', 'left', 'prefix', 'right', 'columns'];
  }

  public function setValue($value): void {
    $this->value = $value;
    $this->row?->setValue($value);
  }

  public function getValue(): mixed {
    return $this->row === null ? parent::getValue() : $this->row->getValue();
  }

  public function setText($text): void {
    $this->pendingText = (string)$text;
    $this->row?->setText($text);
  }

  public function addText(string $text): void {
    $this->setText(trim($this->pendingText . ' ' . $text));
  }

  public function getText(?array &$text = null): string {
    return $this->row === null ? $this->pendingText : $this->row->getText();
  }

  public function setSelectable($value): void {
    $this->row?->setSelectable($value);
  }

  public function isSelectable(): bool|string {
    return $this->row === null ? false : $this->row->isSelectable();
  }

  public function setSelected($value): void {
    $this->row?->setSelected($value);
  }

  public function isSelected(): bool {
    return $this->row !== null && $this->row->isSelected();
  }

  public function select($marker = false): void {
    $this->row?->select($marker);
  }

  public function deselect(): void {
    $this->row?->deselect();
  }

  public function markSelected($marker = false): void {
    $this->row?->markSelected($marker);
  }

  public function setFilterable($value): void {
    $this->row?->setFilterable($value);
  }

  public function isFilterable(): bool {
    return $this->row !== null && $this->row->isFilterable();
  }

  public function setLeft($value): void {
    $this->row?->setLeft($value);
  }

  public function setPrefix($value): void {
    $this->row?->setPrefix($value);
  }

  public function setRight($value): void {
    $this->row?->setRight($value);
  }

  public function setColumns($value): void {
    $this->row?->setColumns($value);
  }

  public function addRightClass(string $class): void {
    $this->row?->addClass($class);
  }

  public function addClass(string $class): void {
    parent::addClass($class);
    $this->row?->addClass($class);
  }

  public function removeClass(string $class): void {
    parent::removeClass($class);
    $this->row?->removeClass($class);
  }

  public function addVariant(string $class): void {
    parent::addVariant($class);
    $this->row?->addVariant($class);
  }

  public function removeVariant(string $class): void {
    parent::removeVariant($class);
    $this->row?->removeVariant($class);
  }

  public function hasVariant(string $class): bool {
    return $this->row === null ? parent::hasVariant($class) : $this->row->hasVariant($class);
  }

  public function match($search): bool {
    return $this->row !== null && $this->row->match($search);
  }

  public function getWidth(): int {
    if ($this->row === null) {
      return 0;
    }
    return mb_strlen($this->row->getLeft() . $this->row->getPrefix() . $this->row->getText() . $this->row->getRight()) * 10;
  }

}
