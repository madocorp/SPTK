<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class File extends Element {

  private $elementFile;
  private $elementBrowse;
  private $placeholder = '';
  private $start =  '';
  private $fileFilter = true;
  private $create = false;

  protected function init(): void {
    $this->acceptInput = true;
    $this->value = '';
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementFile = new InputValue($this);
    $this->elementBrowse = new Element($this, null, null, 'Browse');
    $this->elementBrowse->addText('..');
  }

  public function getAttributeList(): array {
    return ['value', 'placeholder', 'start', 'file', 'create'];
  }

  public function setValue($value): void {
    if ($value === false) {
      return;
    }
    $this->value = $value;
    $this->elementFile->setValue($this->value);
  }

  public function setPlaceholder($value) {
    $this->placeholder = $value;
  }

  public function setStart($value) {
    if ($value === '~') {
      $value = getenv('HOME') ?: getenv('USERPROFILE');
      if (!$value) {
        $value = '.';
      }
    }
    $path = realpath($value);
    if ($path !== false && is_dir($path)) {
      $this->start = $path;
    }
  }

  public function setFile($value) {
    if ($value === 'true') {
      $this->fileFilter = true;
    } else if ($value === 'false') {
      $this->fileFilter = false;
    } else {
      $this->fileFilter = explode(',', $value);
    }
  }

  public function setCreate($value) {
    $this->create = ($value === 'true');
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      $this->elementBrowse->addVariant('active');
      if ($this->value === '') {
        $this->elementFile->setValue($this->placeholder);
        $this->elementFile->addVariant('placeholder');
      }
    }
    parent::addVariant($class);
  }

  public function removeVariant(string $class): void {
    if ($class == 'active') {
      $this->elementBrowse->removeVariant('active');
      if ($this->value === '') {
        $this->elementFile->setValue($this->value);
        $this->elementFile->removeVariant('placeholder');
      }
    }
    parent::removeVariant($class);
  }

  public function selected($path) {
    $this->setValue($path);
    $this->addVariant('active');
    Element::refresh();
  }

  private function openFilePanel() {
    $window = $this->findAncestorByType('Window');
    $panel = new FilePanel($window);
    $panel->setFileFilter($this->fileFilter);
    if (!empty($this->value)) {
      $panel->setPath($this->value);
    } else {
      $panel->setPath($this->start);
    }
    $panel->setCreate($this->create);
    $panel->setOnSelect([$this, 'selected']);
    $panel->show();
    if ($this->value === '') {
      $this->elementFile->setValue($this->value);
      $this->elementFile->removeVariant('placeholder');
    }
    Element::refresh();
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
      case Action::DO_IT:
        $this->openFilePanel();
        return true;
      case Action::DELETE_FORWARD:
      case Action::DELETE_BACK:
        $this->setValue('');
        Element::immediateRender($this);
        return true;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
    return true;
  }

}
