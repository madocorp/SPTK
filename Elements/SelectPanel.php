<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class SelectPanel extends Panel {

  private $theList;
  private $title;
  private $buttons;
  private $onSelect = false;
  private $options = [];
  private $multiple = false;
  private $multipleButtonsAdded = false;

  protected function init(): void {
    parent::init();
    $listName = null;
    if (is_string($this->name) && str_ends_with($this->name, '/panel')) {
      $listName = substr($this->name, 0, -strlen('/panel')) . '/list';
    }
    $this->title = new Element($this, null, null, 'PanelTitle');
    $this->title->addVariant('active');
    $content = new Element($this, null, null, 'PanelContent');
    $label = new Element($content, null, null, 'Label');
    $this->theList = new ListBox($label, $listName, 'select-panel-list');
    $this->theList->setTyping('search');
    $this->theList->setOnSelect([$this, 'choose']);
    $this->buttons = new Element($content, null, null, 'ButtonBox');
    $ok = new Button($this->buttons);
    $ok->setHotKey('RETURN');
    $ok->addText('OK');
    $ok->setOnPress([$this, 'choose']);
    new Space($this->buttons);
    $cancel = new Button($this->buttons);
    $cancel->setHotKey('ESCAPE');
    $cancel->addText('Cancel');
    $cancel->setOnPress([$this, 'cancel']);
  }

  public function setTitle($title): void {
    $this->title->setText($title === '' ? 'Select' : $title);
  }

  public function setOnSelect($callback): void {
    $this->onSelect = $callback;
  }

  public function setMultiple($value): void {
    $this->multiple = ($value === true || $value === 'true');
    if (!$this->multiple || $this->multipleButtonsAdded) {
      return;
    }
    $this->theList->setOnSelect([$this, 'selectionChanged']);
    new Space($this->buttons);
    $all = new Button($this->buttons);
    $all->setHotKey('INSERT');
    $all->addText('All');
    $all->setOnPress([$this, 'selectAll']);
    new Space($this->buttons);
    $clear = new Button($this->buttons);
    $clear->setHotKey('DELETE');
    $clear->addText('Clear');
    $clear->setOnPress([$this, 'clearSelection']);
    $this->multipleButtonsAdded = true;
  }

  public function setOptions(array $options, mixed $selected = false): void {
    $this->options = $options;
    $this->theList->clear();
    $cursor = 0;
    foreach ($this->options as $i => $option) {
      $item = new ListItem($this->theList);
      $item->setValue((string)$option);
      $item->setFilterable(true);
      if ($this->multiple) {
        $item->setSelectable(true);
      } else if ($option === $selected) {
        $cursor = $i;
      }
    }
    if ($this->multiple) {
      $selectedValues = array_values(array_filter(
        array_map('trim', explode(',', (string)$selected)),
        fn($value) => $value !== ''
      ));
      $this->theList->setValueType('select');
      $this->theList->setSelectOnReturn(false);
      $this->theList->setSelectedValues($selectedValues);
      foreach ($this->options as $i => $option) {
        if (in_array((string)$option, $selectedValues, true)) {
          $cursor = $i;
          break;
        }
      }
    }
    if (!empty($this->options)) {
      $this->theList->moveCursor($cursor);
    }
    if ($this->findAncestorByType('Window') !== false) {
      $this->theList->recalculateGeometry();
    }
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

  public function selectionChanged($item = false): bool {
    return true;
  }

  public function selectAll($panel = false): bool {
    $this->theList->selectAll();
    if ($this->findAncestorByType('Window') !== false) {
      $this->theList->recalculateGeometry();
      Element::immediateRender($this);
    }
    return true;
  }

  public function clearSelection($panel = false): bool {
    $this->theList->clearSelection();
    if ($this->findAncestorByType('Window') !== false) {
      $this->theList->recalculateGeometry();
      Element::immediateRender($this);
    }
    return true;
  }

  public function cancel(): bool {
    $this->hide();
    $this->remove();
    Element::refresh();
    return true;
  }

  public function keyPressHandler($element, $event): bool {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
      case Action::SELECT_ITEM:
        return $this->choose();
      case Action::CLOSE:
        return $this->cancel();
    }
    return parent::keyPressHandler($element, $event);
  }

}
