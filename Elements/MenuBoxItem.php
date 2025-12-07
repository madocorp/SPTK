<?php

namespace SPTK;

class MenuBoxItem extends Box {

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
    $this->selectField = new MenuBoxItemLeft($this);
  }

  public function getAttributeList() {
    return ['submenu', 'radio', 'selectable', 'selected', 'filterable', 'onOpen', 'onSelect'];
  }

  public function setSubmenu($value) {
    if ($value === 'true') {
      $this->submenu = true;
      $mbir = new MenuBoxItemRight($this);
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

  protected function parseCallback($value) {
    if (empty($value)) {
      return false;
    }
    $function = explode('::', $value);
    if (!is_array($function) || count($function) !== 2) {
      throw new \Exception("Malformed callback function: '{$value}'");
    }
    return $function;
  }

  public function setOnOpen($value) {
    $this->onOpen = $this->parseCallback($value);
  }

  public function setOnSelect($value) {
    $this->onSelect = $this->parseCallback($value);
  }

  protected function openSubmenu() {
    $submenu = $this->findParentByType('SubMenu');
    foreach ($submenu->descendants as $menuBox) {
      if ($menuBox->belongsTo == $this->id) {
        $x = $this->geometry->x + $this->geometry->width;
        $y = $this->geometry->y + floor($this->geometry->height / 2);
        $submenu->showMenuBox($this->id, $x, $y, false);
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
          call_user_func($this->onSelect, $this->selected);
        }
      }
      if ($this->submenu) {
        return $this->openSubmenu();
      }
      $menu = $this->findParentByType('Menu');
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
          call_user_func($this->onSelect, $this->selected);
        }
        Element::refresh();
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
