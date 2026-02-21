<?php

namespace SPTK\Elements;

use \SPTK\Element;

class MenuBarItem extends Element {

  public function setHotKey($hotKeyStr) {
    $hotKey = new Element($this, false, false, 'MenuBarHotKey');
    $text = new Word($hotKey);
    $text->setValue($hotKeyStr);
  }

}
