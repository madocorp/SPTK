<?php

namespace SPTK;

class MenuBoxItem extends Box {

  protected $submenu = false;
  protected $onOpen = false;

  protected function init() {
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList() {
    return ['submenu', 'selectable', 'filterable', 'onOpen'];
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
  }

  public function setFilterable($value) {
  }

  public function setOnOpen($value) {
    if (!empty($value)) {
      $function = explode('::', $value);
      if (!is_array($function) || count($function) !== 2) {
        throw new \Exception("Malformed callback function: '{$value}'");
      }
      $this->onOpen = $function;
    }
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    if ($event['key'] == KeyCode::RETURN) {
      if ($this->onOpen !== false) {
        call_user_func($this->onOpen);
      }
      return true;
    }
    if ($event['key'] == KeyCode::RIGHT) {
      if ($this->submenu) {
        $submenu = $this->ancestor->ancestor;
        foreach ($submenu->descendants as $menuBox) {
          if ($menuBox->belongsTo == $this->id) {
            $x = $this->geometry->x + $this->geometry->width;
            $y = $this->geometry->y + floor($this->geometry->height / 2);
            $submenu->showMenuBox($this->id, $x, $y, false);
            return true;
          }
        }
      }
    }
    return false;
  }

}
