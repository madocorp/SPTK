<?php

namespace SPTK;

class File extends Element {

  private $file;
  private $elementFile;
  private $elementBrowse;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->elementFile = new InputValue($this);
    $this->setValue('/');
    $this->elementBrowse = new Element($this, false, false, 'Browse');
    $this->elementBrowse->addText('..');
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementBrowse->addClass('active', true);
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementBrowse->removeClass('active', true);
    }
    parent::removeClass($class, $dynamic);
  }

  public function setValue($value) {
    if ($value === false) {
      return;
    }
    $this->file = $value;
    $this->elementFile->setValue($this->file);
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        $this->openFilePanel();
        return true;
    }
    return false;
  }

  private function openFilePanel() {
    $window = $this->findAncestorByType('Window'); // ???
    $panel = new FilePanel($window);
    $panel->setDir('');
    $panel->show();
    Element::refresh();
  }

}
