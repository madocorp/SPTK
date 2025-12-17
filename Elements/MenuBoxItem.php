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

  public function getValue() {
    foreach ($this->descendants as $descendant) {
      if ($descendant->type == 'Word') {
        return $descendant->getValue();
      }
    }
    return false;
  }

  public function getAttributeList() {
    return ['submenu', 'radio', 'selectable', 'selected', 'filterable', 'onOpen', 'onSelect'];
  }

  public function setSubmenu($value) {
    if ($value === 'true') {
      $this->submenu = true;
      $mbir = new Element($this, false, false, 'MenuBoxItemRight');
      $word = new Word($mbir);
      $word->setValue('>');
    }
  }

  public function setSelectable($value) {
    if ($value === 'true') {
      $this->selectable = true;
      $this->selectWord = new Word($this->selectField);
      $this->selectWord->setValue(' ');
    }
  }

  public function setRadio($value) {
    if ($value !== 'false') {
      $this->radio = $value;
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

  protected function openSubmenu() {
    $submenu = $this->findAncestorByType('SubMenu');
    foreach ($submenu->descendants as $menuBox) {
      if ($menuBox->belongsTo == $this->name) {
        $x = $this->geometry->x + $this->geometry->width;
        $y = $this->geometry->y + floor($this->geometry->height / 2);
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
    if ($event['key'] == KeyCode::RETURN) {
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
    }
    if ($event['key'] == KeyCode::SPACE) {
      if ($this->selectable !== false || $this->radio !== false) {
        $this->setSelected(!$this->selected);
        if ($this->onSelect !== false) {
          call_user_func($this->onSelect, $this, $this->selected);
        }
        Element::immediateRender($this->ancestor);
        return true;
      }
    }
    if ($event['key'] == KeyCode::RIGHT) {
      if ($this->submenu) {
        return $this->openSubmenu();
      }
    }
    return false;
  }

}
