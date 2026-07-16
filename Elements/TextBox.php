<?php

namespace SPTK\Elements;

use \SPTK\SDLWrapper\Action;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\Clipboard;

class TextBox extends TextEditor {

  protected function init(): void {
    parent::init();
    $this->removeEvent('TextInput');
  }

  public function insertText(string $text): void {
  }

  public function replaceText(string $text): void {
  }

  public function textInputHandler($element, $event) {
    return false;
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
    $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
    $handled = $this->cursor->handleKeys($keycombo, $linesOnScreen, $lettersOnScreen);
    if ($handled) {
      $this->update();
      return true;
    }
    switch ($keycombo) {
      case Action::COPY:
        Clipboard::set($this->cursor->getSelection());
        $this->cursor->resetSelection();
        break;
      default:
        return false;
    }
    $this->update();
    return true;
  }

}
