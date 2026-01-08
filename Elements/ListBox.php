<?php

namespace SPTK;

class ListBox extends Element {

  protected $activeItem = 0;
  protected $num = 0;
  protected $movable = false;
  protected $selectable = false;
  protected $onChange = false;
  protected $onSelect = false;
  protected $valueType = false;
  protected $pageSize = 1;
  protected $typing = false;
  protected $typed = '';
  protected $activeBeforeType = 0;
  protected $nextMatch = 0;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    return ['movable', 'onChange', 'typing', 'onSelect', 'valueType'];
  }

  public function setMovable($value) {
    $this->movable = ($value === 'true');
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
    $this->onSelect = self::parseCallback($value);
  }

  public function setValueType($value) {
    $this->valueType = $value;
  }

  public function getValue() {
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
  }

  public function getOrderValue() {
    $values = [];
    foreach ($this->descendants as $descendant) {
      $values[] = $descendant->getValue();
    }
    return $value;
  }

  public function getSelectedValue() {
    $selected = [];
    foreach ($this->descendants as $item) {
      if ($item->isSelectable() === true && $item->isSelected()) {
        $selected[] = $item->getValue;
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

  protected function addDescendant($element) {
    $this->num++;
    parent::addDescendant($element);
  }

  protected function removeDescendant($element) {
    $this->num--;
    parent::removeDescendant($element);
    if ($this->activeItem >= $this->num) {
      $this->activeItem = $this->num - 1;
    }
    $this->activateItem();
  }

  public function clear() {
    parent::clear();
    $this->activeItem = 0;
    $this->num = 0;
    $this->scrollY = 0;
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->addClass('active', true);
        }
      }
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      foreach ($this->descendants as $i => $descendant) {
        if ($i === $this->activeItem) {
          $descendant->removeClass('active', true);
        }
      }
    }
    parent::removeClass($class, $dynamic);
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
    if ($this->geometry->contentHeight > $this->geometry->height && $this->scrollY > $this->geometry->contentHeight - $this->geometry->height + $this->geometry->borderTop) {
      $this->scrollY = $this->geometry->contentHeight - $this->geometry->height + $this->geometry->borderTop;
    }
  }

  public function activateItem($direction = 1) {
    foreach ($this->descendants as $descendant) {
      $descendant->removeClass('selected', true);
      $descendant->removeClass('active', true);
    }
    for ($i = 0; $i < $this->num; $i++) {
      $idx = ($this->num + $this->activeItem + $i * $direction) % $this->num;
      $descendant = $this->descendants[$idx];
      if ($descendant->display) {
        $this->activeItem = $idx;
        $descendant->addClass('selected', true);
        $descendant->addClass('active', true);
        if ($descendant->geometry->y + $descendant->geometry->height > $this->scrollY + $this->geometry->height - $this->geometry->borderTop) {
          $this->scrollY = $descendant->geometry->y + $descendant->geometry->height - $this->geometry->height + $this->geometry->borderTop;
        } else if ($descendant->geometry->y < $this->scrollY) {
          $this->scrollY = $descendant->geometry->y - $this->geometry->borderTop;
        }
        break;
      }
    }
    if ($this->onChange !== false) {
      call_user_func($this->onChange, $this);
    }
  }

  public function getActive() {
    return $this->descendants[$this->activeItem];
  }

  protected function calculateHeights() {
    parent::calculateHeights();
    if (!isset($this->descendants[0])) {
      return;
    }
    $item = $this->descendants[0];
    if ($this->geometry->innerHeight === 'content' || $item->geometry->fullHeight == 'content') {
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
          $item->select();
          Element::immediateRender($this);
          if ($this->onSelect !== false) {
            call_user_func($this->onSelect, $item);
          }
        } else if ($selectable !== false) {
          foreach ($this->descendants as $descendant) {
            if ($descendant->id === $item->id) {
              $item->select();
              if ($this->onSelect !== false) {
               call_user_func($this->onSelect, $item);
              }
            } else if ($selectable === $descendant->isSelectable()) {
              $descendant->deselect();
            }
          }
          Element::immediateRender($this);
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
