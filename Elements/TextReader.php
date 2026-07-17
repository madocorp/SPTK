<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\Action;
use \SPTK\SDLWrapper\KeyCombo;

class TextReader extends Element {

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function addVariant(string $class): void {
    if ($class !== 'active') {
      parent::addVariant($class);
      return;
    }
    $class = $this->variantClass($class);
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
    }
  }

  public function removeVariant(string $class): void {
    if ($class !== 'active') {
      parent::removeVariant($class);
      return;
    }
    $class = $this->variantClass($class);
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
    }
  }

  private function variantClass(string $class): string {
    return $this->getType() . ':' . $class;
  }

  public function keyPressHandler($element, $event): bool {
    if (!$this->style->get('scrollable')) {
      return false;
    }
    $action = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $line = max(1, (int)$this->geometry->lineHeight);
    $page = max($line, $this->geometry->innerHeight - $line);
    switch ($action) {
      case Action::MOVE_UP:
        $handled = $this->scrollBy(0, -$line);
        break;
      case Action::MOVE_DOWN:
        $handled = $this->scrollBy(0, $line);
        break;
      case Action::PAGE_UP:
        $handled = $this->scrollBy(0, -$page);
        break;
      case Action::PAGE_DOWN:
        $handled = $this->scrollBy(0, $page);
        break;
      case Action::MOVE_FIRST:
      case Action::MOVE_START:
        $handled = $this->scrollTo($this->scrollX, 0);
        break;
      case Action::MOVE_LAST:
      case Action::MOVE_END:
        $handled = $this->scrollTo($this->scrollX, $this->maxScrollY());
        break;
      default:
        return false;
    }
    if ($handled && $this->renderer instanceof \FFI\CData) {
      Element::refresh();
    }
    return true;
  }

}
