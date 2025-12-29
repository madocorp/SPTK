<?php

namespace SPTK;

class File extends Element {

  private $elementFile;
  private $elementBrowse;
  private $placeholder;
  private $start =  '';
  private $fileFilter = true;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->elementFile = new InputValue($this);
    $this->elementBrowse = new Element($this, false, false, 'Browse');
    $this->elementBrowse->addText('..');
  }

  public function getAttributeList() {
    return ['value', 'placeholder', 'start', 'file'];
// new = true/false: allow name field to save files and create directory option too
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementBrowse->addClass('active', true);
      if (empty($this->value)) {
        $this->elementFile->setValue($this->placeholder);
        $this->elementFile->addClass('placeholder');
      }
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementBrowse->removeClass('active', true);
      if (empty($this->value)) {
        $this->elementFile->setValue($this->value);
        $this->elementFile->removeClass('placeholder');
      }
    }
    parent::removeClass($class, $dynamic);
  }

  public function setPlaceholder($value) {
    $this->placeholder = $value;
  }

  public function setStart($value) {
    $this->start = $value;
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

  public function setValue($value) {
    if ($value === false) {
      return;
    }
    $this->value = $value;
    $this->elementFile->setValue($this->value);
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        $this->openFilePanel();
        return true;
    }
    return false;
  }

  public function selected($path) {
    $this->setValue($path);
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
    $panel->setOnSelect([$this, 'selected']);
    $panel->show();
    $this->elementFile->removeClass('placeholder');
    Element::refresh();
  }

}
