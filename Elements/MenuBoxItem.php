<?php

namespace SPTK;

class MenuBoxItem extends Element {

  protected $submenu = false;
  protected $onOpen = false;
  protected $onSelect = false;
  protected $selectable = false;
  protected $radio = false;
  protected $selected = false;
  protected $selectField = false;
  protected $selectWord = false;

  protected function init() {
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->selectField = new Element($this, false, false, 'MenuBoxItemLeft');
  }

  public function getAttributeList() {
    return ['submenu', 'radio', 'selectable', 'selected', 'filterable', 'onOpen', 'onSelect', 'value'];
  }

  public function setSubmenu($value) {
    if ($value === 'true') {
      $this->submenu = true;
      $mbir = new Element($this, false, false, 'MenuBoxItemRight');
      $word = new Word($mbir);
      $word->setValue('>');
    }
  }

  public function setRadio($value) {
    if ($value !== 'false' && $value !== false) {
      $this->radio = $value;
      $this->selectWord = new Word($this->selectField);
      $this->selectWord->setValue(' ');
    }
  }

  public function setSelectable($value) {
    if ($value === 'true') {
      $this->selectable = true;
      $this->selectWord = new Word($this->selectField);
      $this->selectWord->setValue(' ');
    }
  }

  public function setSelected($value) {
    if ($value === true || $value === 'true') {
      if ($this->selectable) {
        $this->selectWord->setValue('X');
      } else {
        foreach ($this->ancestor->descendants as $element) {
          if ($element->type == 'MenuBoxItem' && $element->radio == $this->radio) {
            $element->selected = false;
            $element->selectWord->setValue(' ');
          }
        }
        $this->selectWord->setValue('*');
      }
      $this->selected = true;
    } else if ($this->selectable) {
      $this->selectWord->setValue(' ');
      $this->selected = false;
    }
  }

  public function setFilterable($value) {
  }

  public function setOnOpen($value) {
    $this->onOpen = self::parseCallback($value);
  }

  public function setOnSelect($value) {
    $this->onSelect = self::parseCallback($value);
  }

  public function getValue() {
    if ($this->value === false || $this->value === '') {
      return $this->getText();
    }
    return $this->value;
  }

  public function getWidth() {
    $width = 0;
    foreach ($this->descendants as $descendant) {
      if ($descendant->type == 'Word') {
        $width += $descendant->getWidth();
        $width += $descendant->style->get('wordSpacing');
      }
    }
    return $width + 30;
  }

  protected function openSubmenu() {
    $submenu = $this->findAncestorByType('SubMenu');
    foreach ($submenu->descendants as $menuBox) {
      if ($menuBox->belongsTo == $this->name) {
        self::getRelativePos($submenu->id, $this, $x, $y);
        $x += $this->geometry->width;
        $y += floor($this->geometry->height / 2) - $menuBox->geometry->marginTop - $menuBox->geometry->borderTop;
        $submenu->showMenuBox($this->name, $x, $y, false);
        return true;
      }
    }
    return false;
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
        if ($this->selectable !== false || $this->radio !== false) {
          $this->setSelected(!$this->selected);
          if ($this->onSelect !== false) {
            call_user_func($this->onSelect, $this, $this->selected);
          }
        }
        if ($this->submenu) {
          return $this->openSubmenu();
        }
        $menu = $this->findAncestorByType('Menu');
        $menu->closeMenu();
        if ($this->onOpen !== false) {
          call_user_func($this->onOpen);
        }
        return true;
      case Action::SELECT_ITEM:
        if ($this->selectable !== false || $this->radio !== false) {
          $this->setSelected(!$this->selected);
          if ($this->onSelect !== false) {
            call_user_func($this->onSelect, $this, $this->selected);
          }
          Element::immediateRender($this->ancestor);
          return true;
        }
        break;
      case Action::MOVE_RIGHT:
        if ($this->submenu) {
          return $this->openSubmenu();
        }
        break;
    }
    return false;
  }

}
