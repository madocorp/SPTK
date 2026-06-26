<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class Select extends Element {

  private $elementValue;
  private $elementOpen;
  private $hint = '';
  private $options = [];

  protected function init(): void {
    $this->acceptInput = true;
    $this->value = '';
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementValue = new InputValue($this);
    $this->elementOpen = new Element($this, null, null, 'Browse');
    $this->elementOpen->addText('>>');
  }

  public function getAttributeList(): array {
    return ['value', 'hint', 'options'];
  }

  public function postInit(): void {
    foreach ($this->descendants as $descendant) {
      if ($descendant->getType() === 'Option') {
        $value = $descendant->getValue();
        if ($value !== false && $value !== '') {
          $this->options[] = $value;
        }
      }
    }
    $this->refreshValue();
  }

  public function setValue($value): void {
    if ($value === false) {
      return;
    }
    $this->value = (string)$value;
    $this->refreshValue();
  }

  public function setHint($value): void {
    if ($value !== false) {
      $this->hint = (string)$value;
      $this->refreshValue();
    }
  }

  public function setOptions($options): void {
    if ($options === false) {
      return;
    }
    if (is_array($options)) {
      $this->options = array_values($options);
    } else {
      $this->options = array_values(array_filter(array_map('trim', explode(',', $options)), fn($option) => $option !== ''));
    }
  }

  public function getOptions(): array {
    return $this->options;
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      $this->elementOpen->addVariant('active');
    }
    parent::addVariant($class);
  }

  public function removeVariant(string $class): void {
    if ($class == 'active') {
      $this->elementOpen->removeVariant('active');
    }
    parent::removeVariant($class);
  }

  public function selected($value): void {
    $this->setValue($value);
    $this->addVariant('active');
    Element::refresh();
  }

  private function refreshValue(): void {
    if ($this->value === '' && $this->hint !== '') {
      $this->elementValue->setValue($this->hint);
      $this->elementValue->addVariant('placeholder');
    } else {
      $this->elementValue->setValue($this->value);
      $this->elementValue->removeVariant('placeholder');
    }
  }

  private function openSelectPanel(): void {
    $window = $this->findAncestorByType('Window');
    if ($window === false) {
      return;
    }
    $panel = new SelectPanel($window);
    $panel->setTitle($this->hint);
    $panel->setOptions($this->options, $this->value);
    $panel->setOnSelect([$this, 'selected']);
    $panel->show();
    Element::refresh();
  }

  public function keyPressHandler($element, $event): bool {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
      case Action::DO_IT:
        $this->openSelectPanel();
        return true;
      case Action::DELETE_FORWARD:
      case Action::DELETE_BACK:
        $this->setValue('');
        Element::immediateRender($this);
        return true;
    }
    return false;
  }

  public function textInputHandler($element, $event): bool {
    return true;
  }

}
