<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class Button extends Element {

  protected $onPress = false;
  protected $hotKeyStr = false;
  protected $panel = false;
  protected $default = false;
  protected $defaultExplicit = false;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList(): array {
    return ['hotKey', 'onPress', 'default'];
  }

  public function setHotKey($hotKeyStr) {
    if ($hotKeyStr === false) {
      return;
    }
    if (!defined("\SPTK\SDLWrapper\KeyCode::{$hotKeyStr}")) {
      echo "KeyCode {$hotKeyStr} is not defined!";
      return;
    }
    $hotKey = new Element($this, null, null, 'ButtonHotKey');
    $text = new Word($hotKey);
    $text->setValue($hotKeyStr);
    $this->hotKeyStr = $hotKeyStr;
    if ($hotKeyStr === 'RETURN' && !$this->defaultExplicit) {
      $this->default = true;
    }
    $this->registerPanelActions();
  }

  public function setOnPress($value) {
    if ($value === false) {
      return;
    }
    if (is_array($value)) {
      $this->onPress = $value;
    } else {
      $this->onPress = self::parseCallback($value);
    }
    $this->registerPanelActions();
  }

  public function setDefault($value): void {
    if ($value === false) {
      return;
    }
    $this->default = ($value === true || $value === 'true');
    $this->defaultExplicit = true;
    $this->registerPanelActions();
  }

  private function registerPanelActions(): void {
    if ($this->onPress === false) {
      return;
    }
    $this->findPanel();
    if ($this->panel === false) {
      return;
    }
    if ($this->hotKeyStr !== false) {
      $this->panel->addHotKey(constant("\SPTK\SDLWrapper\KeyCode::{$this->hotKeyStr}"), $this->onPress);
    }
    if ($this->default) {
      $this->panel->setDefaultButtonAction($this->onPress);
    } else {
      $this->panel->clearDefaultButtonAction($this->onPress);
    }
  }

  private function findPanel(): void {
    if ($this->panel !== false) {
      return;
    }
    foreach (['Panel', 'WarningPanel', 'ErrorPanel', 'FilePanel', 'SelectPanel', 'Window'] as $type) {
      $this->panel = $this->findAncestorByType($type);
      if ($this->panel !== false) {
        return;
      }
    }
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
        if ($this->onPress !== false) {
          call_user_func($this->onPress, $this->panel);
        }
        return true;
    }
    return false;
  }

}
