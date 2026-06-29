<?php

namespace SPTK\Elements;

use \SPTK\Element;

class ListItem extends Element {

  private const EXTRA_WIDTH = 30;

  protected $selected = false;
  protected $selectable = false;
  protected $filterable = false;
  protected $pre = '';
  protected $after;
  protected $itemLeft;
  protected $itemRight;
  protected $valueField;
  protected $matchField;
  protected $afterMatchField = '';
  protected $matched = false;
  protected $text = '';
  protected $initialized = false;

  protected function init(): void {
    $this->itemLeft = new Element($this, null, null, 'ItemLeft');
    $this->itemRight = new Element($this, null, null, 'ItemRight');
    $this->valueField = new InputValue($this);
    $this->matchField = new InputValue($this);
    $this->matchField->addVariant('matched');
    $this->afterMatchField = new InputValue($this);
    $this->initialized = true;
  }

  public function postInit(): void {
    $text = [];
    foreach ($this->descendants as $descendant) {
      if ($descendant->type === 'Word') {
        $this->removeDescendant($descendant);
        $text[] = $descendant->getValue();
      }
    }
    $this->text = implode(' ', $text);
    if ($this->text !== '') {
      $this->valueField->setValue($this->text);
    }
  }

  public function getAttributeList(): array {
    return ['value', 'selectable', 'selected', 'filterable', 'left', 'right'];
  }

  public function setValue($value): void {
    $this->value = $value;
    $this->text = $value;
    $this->valueField->setValue($this->text);
  }

  public function setSelectable($value) {
    if ($value === true || $value === 'true') {
      $this->selectable = true;
    } else if ($value === 'false') {
      $this->selectable = false;
    } else {
      $this->selectable = $value;
    }
  }

  public function setSelected($value) {
    if ($value === true || $value === 'true') {
      $this->select();
    }
  }

  public function setFilterable($value) {
    $this->filterable = ($value === true || $value === 'true');
  }

  public function setLeft($value) {
    if ($value !== false) {
      $this->itemLeft->setText($value);
    }
  }

  public function setRight($value) {
    if ($value !== false) {
      $this->itemRight->setText($value);
    }
  }

  public function setText($text): void {
    $this->text = $text;
    $this->valueField->setValue($this->text);
  }

  public function isSelectable() {
    return $this->selectable;
  }

  public function isFilterable() {
    return $this->filterable;
  }

  public function isSelected() {
    return $this->selected;
  }

  public function getValue(): mixed {
    if ($this->value === false || $this->value === '') {
      return $this->text;
    }
    return $this->value;
  }

  protected function getElementWidth(Element $element) {
    if (method_exists($element, 'getWidth')) {
      return $element->getWidth();
    }
    if (is_int($element->geometry->width) && $element->geometry->width > 0) {
      return $element->geometry->width;
    }
    $styleWidth = $element->style->get('width', $this->geometry);
    if (is_int($styleWidth) && $styleWidth > 0) {
      return $styleWidth;
    }
    $width = 0;
    foreach ($element->descendants as $descendant) {
      $width += $this->getElementWidth($descendant);
    }
    return $width;
  }

  public function getWidth() {
    $width = $this->getElementWidth($this->itemLeft);
    $width += $this->getElementWidth($this->valueField);
    $width += $this->getElementWidth($this->matchField);
    $width += $this->getElementWidth($this->afterMatchField);
    $width += $this->getElementWidth($this->itemRight);
    return $width + self::EXTRA_WIDTH;
  }

  public function deselect() {
    $this->selected = false;
    $this->itemLeft->clear();
    $this->removeVariant('selected');
  }

  public function select($marker = false) {
    if ($this->selected && $this->selectable === true) {
      $this->selected = false;
      $this->itemLeft->clear();
      $this->removeVariant('selected');
    } else {
      $this->selected = true;
      $this->addVariant('selected');
      if ($marker !== false) {
        $this->itemLeft->setText((string) $marker);
      } else if ($this->selectable === true) {
        $this->itemLeft->setText('X');
      } else {
        $this->itemLeft->setText('*');
      }
    }
  }

  public function markSelected($marker = false) {
    if ($this->selected) {
      $this->itemLeft->setText((string) $marker);
    }
  }

  public function match($search) {
    if ($this->filterable === false) {
      return false;
    }
    if ($this->text !== '' && $search !== false) {
      $pos = strpos($this->text, $search);
      if ($pos === 0) {
        $slen = mb_strlen($search);
        $this->matched = true;
        $before = '';
        $match = mb_substr($this->text, $pos, $slen);
        $after = mb_substr($this->text, $pos + $slen);
        $this->valueField->setValue($before);
        $this->matchField->setValue($match);
        $this->afterMatchField->setValue($after);
        return true;
      }
    }
    if ($this->matched) {
      $this->valueField->setValue($this->text);
      $this->matchField->setValue('');
      $this->afterMatchField->setValue('');
    }
    return false;
  }

  public function addDescendant($element): void {
    parent::addDescendant($element);
    if ($this->initialized) {
      $this->valueField->setValue('');
    }
  }

}
