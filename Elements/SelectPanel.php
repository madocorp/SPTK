<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class SelectPanel extends Panel {

  private $theList;
  private $title;
  private $onSelect = false;
  private $options = [];

  protected function init(): void {
    parent::init();
    $this->title = new Element($this, null, null, 'PanelTitle');
    $content = new Element($this, null, null, 'PanelContent');
    $label = new Element($content, null, null, 'Label');
    $this->theList = new ListBox($label, null, 'wh50');
    $this->theList->setTyping('search');
    $this->theList->setOnSelect([$this, 'choose']);
  }

  public function setTitle($title): void {
    $this->title->setText($title === '' ? 'Select' : $title);
  }

  public function setOnSelect($callback): void {
    $this->onSelect = $callback;
  }

  public function setOptions(array $options, mixed $selected = false): void {
    $this->options = $options;
    $this->theList->clear();
    $cursor = 0;
    foreach ($this->options as $i => $option) {
      $item = new ListItem($this->theList);
      $item->setValue((string)$option);
      $item->setFilterable(true);
      if ($option === $selected) {
        $cursor = $i;
      }
    }
    if (!empty($this->options)) {
      $this->theList->moveCursor($cursor);
    }
    $this->theList->recalculateGeometry();
  }

  public function choose(): bool {
    if (empty($this->options)) {
      return true;
    }
    $value = $this->theList->getValue();
    $this->hide();
    $this->remove();
    Element::refresh();
    if ($this->onSelect !== false) {
      call_user_func($this->onSelect, $value);
    }
    return true;
  }

  public function keyPressHandler($element, $event): bool {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
      case Action::SELECT_ITEM:
        return $this->choose();
      case Action::CLOSE:
        $this->hide();
        $this->remove();
        Element::refresh();
        return true;
    }
    return parent::keyPressHandler($element, $event);
  }

}
