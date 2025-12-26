<?php

namespace SPTK;

class Button extends Element {

  protected $onPress = false;
  protected $hotKeyStr = false;
  protected $panel = false;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    return ['hotKey', 'onPress'];
  }

  public function setHotKey($hotKeyStr) {
    if (!defined("\SPTK\KeyCode::{$hotKeyStr}")) {
      echo "KeyCode {$hotKeyStr} is not defined!";
      return;
    }
    $hotKey = new Element($this, false, false, 'ButtonHotKey');
    $text = new Word($hotKey);
    $text->setValue($hotKeyStr);
    $this->hotKeyStr = $hotKeyStr;
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
    if ($this->hotKeyStr !== false) {
      foreach (['Panel', 'WarningPanel', 'ErrorPanel', 'FilePanel', 'Window'] as $type) {
        $this->panel = $this->findAncestorByType($type);
        if ($this->panel !== false) {
          break;
        }
      }
      $this->panel->addHotKey(constant("\SPTK\KeyCode::{$this->hotKeyStr}"), $this->onPress);
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
