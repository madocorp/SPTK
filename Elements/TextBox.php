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

  protected function drawCursor(): bool {
    return $this->active;
  }

  protected function collapsedSelectionVisible(): bool {
    return $this->active;
  }

  protected function cursorColors(): array {
    return [
      'fg' => $this->style->get('backgroundColor'),
      'bg' => $this->style->get('color')
    ];
  }

  protected function selectionColors(string $styleClass): array {
    return $this->cursorColors();
  }

  public function textInputHandler($element, $event) {
    return false;
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $linesOnScreen = (int)($this->viewportHeight() / $this->rowHeight()) - 1;
    $lettersOnScreen = (int)($this->viewportWidth() / $this->columnWidth());
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
