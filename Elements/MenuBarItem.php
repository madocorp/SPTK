<?php

namespace SPTK;

class MenuBarItem extends Box {

  public function setHotKey($hotKeyStr) {
    $hotKey = new MenuBarHotKey($this);
    $text = new Word($hotKey);
    $text->setValue($hotKeyStr);
  }

}
