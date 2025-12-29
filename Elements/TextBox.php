<?php

namespace SPTK;

class TextBox extends Element {

  private $fontSize;
  private $lineHeight;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->fontSize = $this->style->get('fontSize', $this->ancestor->geometry);
    $this->lineHeight = $this->style->get('lineHeight', $this->ancestor->geometry);
  }

  public function getAttributeList() {
    return ['file'];
  }

  public function setFile($file) {
    if ($file === false) {
      return;
    }
    if (strpos($file, '/') !== 0) {
      if (defined('APP_DIR')) {
        $file = APP_DIR . '/' . $file;
      } else {
        $file = getcwd() . '/' . $file;
      }
    }
    if (!file_exists($file)) {
      return;
    }
    $content = file($file, FILE_IGNORE_NEW_LINES);
    if ($content === false) {
      return;
    }
    foreach ($content as $line) {
      $words = explode(' ', $line);
      foreach ($words as $word) {
        $w = new Word($this);
        $w->setValue($word);
      }
      new Element($this, false, false, 'NL');
    }
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    switch ($a = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {

      case Action::MOVE_LEFT:
        $this->scrollX -= $this->fontSize;
        if ($this->scrollX < 0) {
          $this->scrollX = 0;
        }
        Element::refresh();
        return true;
      case Action::MOVE_RIGHT:
        $this->scrollX += $this->fontSize;
        $maxScrollX = $this->geometry->contentWidth + $this->geometry->paddingRight - ($this->geometry->width - $this->geometry->borderLeft - $this->geometry->borderRight);
        if ($this->scrollX > $maxScrollX) {
          $this->scrollX = $maxScrollX;
        }
        Element::refresh();
        return true;
      case Action::MOVE_UP:
        $this->scrollY -= $this->lineHeight;
        if ($this->scrollY < 0) {
          $this->scrollY = 0;
        }
        Element::refresh();
        return true;
      case Action::MOVE_DOWN:
        $this->scrollY += $this->lineHeight;
        $maxScrollY = $this->geometry->contentHeight + $this->geometry->paddingBottom - ($this->geometry->height - $this->geometry->borderTop - $this->geometry->borderBottom);
        if ($this->scrollY > $maxScrollY) {
          $this->scrollY = $maxScrollY;
        }
        Element::refresh();
        return true;
    }
    return false;
  }


}