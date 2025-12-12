<?php

namespace SPTK;

class Button extends Element {

  protected $onPress = false;
  protected $hotKeyStr = false;

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
    $this->onPress = self::parseCallback($value);
    if ($this->hotKeyStr !== false) {
      foreach (['Panel', 'WarningPanel', 'ErrorPanel', 'Window'] as $type) {
        $panel = $this->findAncestorByType($type);
        if ($panel !== false) {
          break;
        }
      }
      $panel->addHotKey(constant("\SPTK\KeyCode::{$this->hotKeyStr}"), $this->onPress);
    }
  }

  public function keyPressHandler($element, $event) {
    if ($event['key'] == KeyCode::RETURN && $event['mod'] == 0) {
      if ($this->onPress !== false) {
        call_user_func($this->onPress, $this);
        return true;
      }
    }
    return false;
  }

}
