<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class Tabs extends Element {

  protected $tabs = 0;
  protected $currentTab = 0;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getTabContent() {
    $ci = -1;
    foreach ($this->descendants as $element) {
      if ($element->type !== 'Tab') {
        $ci++;
        if ($ci === $this->currentTab) {
          return $element;
        }
      }
    }
    return false;
  }

  public function addDescendant($element) {
    parent::addDescendant($element);
    if ($element->type === 'Tab') {
      $this->tabs++;
    }
    $this->selectTab();
  }

  public function selectTab($selected = null) {
    if ($selected === null) {
      $selected = $this->currentTab;
    }
    $ti = -1;
    foreach ($this->descendants as $element) {
      if ($element->type === 'Tab') {
        $ti++;
        if ($ti === $selected) {
          $element->addClass('active', true);
        } else {
          $element->removeClass('active', true);
        }
      }
    }
    $ci = -1;
    foreach ($this->descendants as $element) {
      if ($element->type !== 'Tab') {
        $ci++;
        if ($ci === $selected) {
          $element->show();
          $element->raise();
        } else {
          $element->hide();
        }
      }
    }
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    switch ($keycombo) {
      case Action::MOVE_LEFT:
        $this->currentTab--;
        if ($this->currentTab < 0) {
          $this->currentTab = $this->tabs - 1;
        }
        $this->selectTab();
        \SPTK\Element::refresh();
        return true;
      case Action::MOVE_RIGHT:
        $this->currentTab++;
        if ($this->currentTab >= $this->tabs) {
          $this->currentTab = 0;
        }
        $this->selectTab();
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
