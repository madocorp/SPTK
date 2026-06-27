<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class ListBox extends Element {

  protected $activeItem = 0;
  protected $num = 0;
  protected $movable = false;
  protected $selectable = false;
  protected $selectionOrder = false;
  protected $selectedOrder = [];
  protected $onChange = false;
  protected $onSelect = false;
  protected $valueType = false;
  protected $pageSize = 1;
  protected $typing = false;
  protected $typed = '';
  protected $activeBeforeType = 0;
  protected $nextMatch = 0;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList(): array {
    return ['movable', 'selectionOrder', 'onChange', 'typing', 'onSelect', 'valueType'];
  }

  public function setMovable($value) {
    $this->movable = ($value === 'true');
  }

  public function setSelectionOrder($value) {
    $this->selectionOrder = ($value === true || $value === 'true');
  }

  public function setOnChange($value) {
    if ($value === false) {
      return;
    }
    if (is_array($value)) {
      $this->onChange = $value;
    } else {
      $this->onChange = self::parseCallback($value);
    }
  }

  public function setTyping($value) {
    if ($value === 'search') {
      $this->typing = 'search';
      $this->addEvent('TextInput', [$this, 'textInputHandler']);
    } else if ($value === 'filter') {
      $this->typing = 'filter';
      $this->addEvent('TextInput', [$this, 'textInputHandler']);
    } else {
      $this->typing = false;
      $this->removeEvent('TextInput');
    }
  }

  public function setOnSelect($value) {
    if ($value === false) {
      return;
    }
    if (is_array($value)) {
      $this->onSelect = $value;
    } else {
      $this->onSelect = self::parseCallback($value);
    }
  }

  public function setValueType($value) {
    $this->valueType = $value;
  }

  public function getValue(): mixed {
    switch ($this->valueType) {
      case 'order': return $this->getOrderValue();
      case 'select': return $this->getSelectedValue();
      case 'radio': return $this->getRadioValues();
      default: return $this->getSimpleValue();
    }
  }

  public function getSimpleValue() {
    if (!isset($this->descendants[$this->activeItem])) {
      return false;
    }
    $descendant = $this->descendants[$this->activeItem];
    $value = $descendant->getValue();
    if ($value === false || $value === '') {
      $value = $descendant->getText();
    }
    return $value;
  }

  public function getOrderValue() {
    $values = [];
    foreach ($this->descendants as $descendant) {
      $values[] = $descendant->getValue();
    }
    return $values;
  }

  public function getSelectedValue() {
    $selected = [];
    if ($this->selectionOrder) {
      foreach ($this->selectedOrder as $id) {
        foreach ($this->descendants as $item) {
          if ($item->getId() === $id && $item->isSelectable() === true && $item->isSelected()) {
            $selected[] = $item->getValue();
            break;
          }
        }
      }
      return $selected;
    }
    foreach ($this->descendants as $item) {
      if ($item->isSelectable() === true && $item->isSelected()) {
        $selected[] = $item->getValue();
      }
    }
    return $selected;
  }

  public function getRadioValue($group) {
    foreach ($this->descendants as $item) {
      if ($item->isSelectable() === $group && $item->isSelected()) {
        return $item->getValue();
      }
    }
    return false;
  }

  public function getRadioValues() {
    $groups = [];
    foreach ($this->descendants as $item) {
      $selectable = $item->isSelectable();
      if ($selectable !== false && $selectable !== true && $item->isSelected()) {
        $groups[$selectable] = $item->getValue();
      }
    }
    return $groups;
  }

  public function addDescendant($element): void {
    parent::addDescendant($element);
    if ($this->num === 0) {
      $element->addVariant('cursor');
    }
    $this->num++;
  }

  public function removeDescendant($element): void {
    $this->num--;
    parent::removeDescendant($element);
    if ($this->activeItem >= $this->num) {
      $this->activeItem = $this->num - 1;
    }
    $this->activateItem();
  }

  public function clear(): void {
    parent::clear();
    $this->activeItem = 0;
    $this->num = 0;
    $this->scrollY = 0;
    $this->selectedOrder = [];
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->addVariant('active');
        }
      }
    }
    parent::addVariant($class);
  }

  public function removeVariant(string $class): void {
    if ($class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->removeVariant('active');
          $descendant->addVariant('cursor');
        }
      }
    }
    parent::removeVariant($class);
  }

  public function raise(): void {
    parent::raise();
    $this->activateItem();
  }

  public function moveCursor($n, $relative = false) {
    if ($relative) {
      $this->activeItem += $n;
    } else {
      $this->activeItem = $n;
    }
    if ($this->activeItem < 0) {
      $this->activeItem = 0;
    } else if ($this->activeItem >= $this->num) {
      $this->activeItem = $n - 1;
    }
    $this->activateItem();
  }

  public function bringToMiddle() {
    $active = $this->descendants[$this->activeItem];
    $this->scrollY = $active->geometry->y + (int)($active->geometry->height / 2 - $this->geometry->height / 2) + $this->geometry->borderTop;
    if ($this->scrollY < 0) {
      $this->scrollY = 0;
    }
    $maxSY = $this->geometry->contentHeight - $this->geometry->height + $this->geometry->borderTop + $this->geometry->borderBottom;
    if ($this->scrollY > $maxSY) {
      $this->scrollY = $maxSY;
    }
  }

  public function activateItem($direction = 1) {
    foreach ($this->descendants as $descendant) {
      $descendant->removeVariant('selected');
      $descendant->removeVariant('active');
      $descendant->removeVariant('cursor');
    }
    for ($i = 0; $i < $this->num; $i++) {
      $idx = ($this->num + $this->activeItem + $i * $direction) % $this->num;
        $descendant = $this->descendants[$idx];
        if ($descendant->display) {
          $this->activeItem = $idx;
          if ($descendant->isSelectable() !== false) {
            $descendant->addVariant('selected');
          }
          if ($this->hasVariant('active')) {
            $descendant->addVariant('active');
          } else {
            $descendant->addVariant('cursor');
          }
        if (is_int($descendant->geometry->y) && is_int($descendant->geometry->height) && is_int($this->geometry->height)) {
          if ($descendant->geometry->y + $descendant->geometry->height > $this->scrollY + $this->geometry->height - $this->geometry->borderTop) {
            $this->scrollY = $descendant->geometry->y + $descendant->geometry->height - $this->geometry->height + $this->geometry->borderTop;
          } else if ($descendant->geometry->y < $this->scrollY) {
            $this->scrollY = $descendant->geometry->y - $this->geometry->borderTop;
          }
        }
        break;
      }
    }
    if ($this->onChange !== false) {
      call_user_func($this->onChange, $this);
    }
  }

  public function inactivateItem() {
    foreach ($this->descendants as $descendant) {
      $descendant->removeVariant('selected');
      $descendant->removeVariant('active');
    }
  }

  public function setSelectedValues(array $values): void {
    $this->selectedOrder = [];
    foreach ($this->descendants as $item) {
      $item->deselect();
    }
    foreach ($values as $value) {
      foreach ($this->descendants as $item) {
        if ($item->isSelectable() === true && $item->getValue() === $value && !$item->isSelected()) {
          $this->selectItem($item);
          break;
        }
      }
    }
  }

  private function selectItem($item): void {
    if (!$this->selectionOrder) {
      $item->select();
      return;
    }
    $id = $item->getId();
    if ($item->isSelected()) {
      $this->selectedOrder = array_values(array_filter(
        $this->selectedOrder,
        fn($selectedId) => $selectedId !== $id
      ));
      $item->deselect();
    } else {
      $this->selectedOrder[] = $id;
      $item->select(count($this->selectedOrder));
    }
    $this->refreshSelectionOrder();
  }

  private function refreshSelectionOrder(): void {
    if (!$this->selectionOrder) {
      return;
    }
    foreach ($this->descendants as $item) {
      if ($item->isSelectable() === true && $item->isSelected()) {
        $order = array_search($item->getId(), $this->selectedOrder, true);
        if ($order !== false) {
          $item->markSelected($order + 1);
        }
      }
    }
  }

  public function getActive() {
    if (!isset($this->descendants[$this->activeItem])) {
      return false;
    }
    return $this->descendants[$this->activeItem];
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    if ($this->geometry->width === 'calculated') {
      $this->calculateWidth();
    }
    $this->geometry->setDerivedWidths();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateWidth() {
    $width = 0;
    foreach ($this->descendants as $descendant) {
      $dwidth = $descendant->getWidth();
      if ($dwidth > $width) {
        $width = $dwidth;
      }
    }
    $this->geometry->width = max($this->geometry->minWidth, $width);
    $this->geometry->limitateWidth();
    $this->geometry->setDerivedWidths();
  }

  protected function calculateHeights(): void {
    parent::calculateHeights();
    if (!isset($this->descendants[0])) {
      return;
    }
    $item = $this->descendants[0];
    if ($this->geometry->innerHeight === 'content' || $item->geometry->fullHeight == 'content') {
      return;
    }
    if ($this->geometry->innerHeight === 'calculated' || $item->geometry->fullHeight == 'calculated') {
      return;
    }
    $this->pageSize = (int)($this->geometry->innerHeight / $item->geometry->fullHeight);
  }

  public function resetSearch() {
    $this->typed = '';
  }

  protected function lookUp() {
    $filter = ($this->typing === 'filter');
    if ($this->typed === '') {
      foreach ($this->descendants as $i => $descendant) {
        $descendant->match(false);
        if ($filter) {
          $descendant->show();
        }
      }
      $this->activeItem = $this->activeBeforeTyped;
      $this->activateItem();
    } else {
      $matchIndex = false;
      $firstMatchIndex = false;
      $lastMatchIndex = false;
      $matchCount = 0;
      foreach ($this->descendants as $i => $descendant) {
        if (!$descendant->isFilterable()) {
          continue;
        }
        if ($descendant->match($this->typed)) {
          if ($firstMatchIndex === false) {
            $firstMatchIndex = $i;
          }
          $lastMatchIndex = $i;
          if ($matchIndex === false && $matchCount == $this->nextMatch) {
            $matchIndex = $i;
          }
          $matchCount++;
          if ($filter) {
            $descendant->show();
          }
        } else {
          if ($filter) {
            $descendant->hide();
          }
        }
      }
      if ($matchIndex === false && $firstMatchIndex !== false) {
        if ($this->nextMatch < 0) {
          $matchIndex = $lastMatchIndex;
          $this->nextMatch = $matchCount - 1;
        } else {
          $matchIndex = $firstMatchIndex;
          $this->nextMatch = 0;
        }
      }
      if ($matchIndex !== false) {
        $this->moveCursor($matchIndex);
      } else {
        $this->typed = mb_substr($this->typed, 0, -1);
        $this->lookUp();
      }
    }
  }

  protected function nextMatch() {
    $this->nextMatch++;
    $this->lookUp();
    $this->recalculateGeometry();
    $this->bringToMiddle();
    Element::immediateRender($this, false);
  }

  protected function previousMatch() {
    $this->nextMatch--;
    $this->lookUp();
    $this->recalculateGeometry();
    $this->bringToMiddle();
    Element::immediateRender($this, false);
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_UP:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->previousMatch();
          return true;
        }
        if ($this->movable && ($this->typing !== 'filter' || $this->typed === '')) {
          if ($this->activeItem > 0) {
            $item = $this->descendants[$this->activeItem];
            array_splice($this->descendants, $this->activeItem, 1);
            $this->activeItem--;
            array_splice($this->descendants, $this->activeItem, 0, [$item]);
            if ($this->onChange !== false) {
              call_user_func($this->onChange, $this);
            }
          }
          Element::immediateRender($this);
          return true;
        }
        break;
      case Action::SELECT_DOWN:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->nextMatch();
          return true;
        }
        if ($this->movable && ($this->typing !== 'filter' || $this->typed === '')) {
          if ($this->activeItem < $this->num - 1) {
            $item = $this->descendants[$this->activeItem];
            array_splice($this->descendants, $this->activeItem, 1);
            $this->activeItem++;
            array_splice($this->descendants, $this->activeItem, 0, [$item]);
            if ($this->onChange !== false) {
              call_user_func($this->onChange, $this);
            }
          }
          Element::immediateRender($this);
          return true;
        }
        break;
      case Action::MOVE_UP:
        $this->activeItem--;
        if ($this->activeItem < 0) {
          $this->activeItem = 0;
        }
        $this->activateItem(-1);
        Element::immediateRender($this, false);
        return true;
      case Action::MOVE_DOWN:
        $this->activeItem++;
        if ($this->activeItem >= $this->num) {
          $this->activeItem = $this->num - 1;
        }
        $this->activateItem(1);
        Element::immediateRender($this, false);
        return true;
      case Action::MOVE_FIRST:
        $this->activeItem = 0;
        $this->activateItem(1);
        Element::immediateRender($this, false);
        return true;
      case Action::MOVE_LAST:
        $this->activeItem = $this->num - 1;
        $this->activateItem(-1);
        Element::immediateRender($this, false);
        return true;
      case Action::PAGE_UP:
        $this->activeItem -= $this->pageSize - 1;
        if ($this->activeItem < 0) {
          $this->activeItem = 0;
        }
        $this->activateItem(1);
        Element::immediateRender($this, false);
        return true;
      case Action::PAGE_DOWN:
        $this->activeItem += $this->pageSize - 1;
        if ($this->activeItem >= $this->num) {
          $this->activeItem = $this->num - 1;
        }
        $this->activateItem(-1);
        Element::immediateRender($this, false);
        return true;
      case Action::DELETE_BACK:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->nextMatch = 0;
          $this->typed = mb_substr($this->typed, 0, -1);
          $this->lookUp();
          $this->recalculateGeometry();
          $this->bringToMiddle();
          Element::immediateRender($this, false);
          return true;
        }
        return false;
      case Action::DELETE_FORWARD:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->resetSearch();
          $this->lookUp();
          $this->recalculateGeometry();
          $this->bringToMiddle();
          Element::immediateRender($this, false);
          return true;
        }
        return false;
      case Action::SELECT_ITEM:
      case Action::DO_IT:
        $item = $this->descendants[$this->activeItem];
        $selectable = $item->isSelectable();
        if ($selectable === true) {
          $this->selectItem($item);
          Element::immediateRender($this);
          if ($this->onSelect !== false) {
            call_user_func($this->onSelect, $item);
          }
          return true;
      } else if ($selectable !== false) {
        foreach ($this->descendants as $descendant) {
          if ($descendant->id === $item->id) {
            $item->select();
          } else if ($selectable === $descendant->isSelectable()) {
            $descendant->deselect();
          }
        }
        Element::immediateRender($this);
        if ($this->onSelect !== false) {
          call_user_func($this->onSelect, $item);
        }
        return true;
      }
        if ($this->onSelect !== false) {
          Element::immediateRender($this);
          call_user_func($this->onSelect, $item);
          return true;
        }
        return false;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
    if ($this->typed === '') {
      $this->activeBeforeTyped = $this->activeItem;
    }
    $this->nextMatch = 0;
    $this->typed .= $event['text'];
    $this->lookUp();
    $this->recalculateGeometry();
    $this->bringToMiddle();
    Element::immediateRender($this, false);
    return true;
  }

}
