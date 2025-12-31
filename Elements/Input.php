<?php

namespace SPTK;

class Input extends Element {

  private $before = '';
  private $selected = '';
  private $after = '';
  private $elementBefore;
  private $elementSelected;
  private $elementAfter;
  private $selectDirection = 0;
  private $placeholder = '';
  private $onChange = false;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementBefore = new InputValue($this);
    $this->elementSelected = new InputValue($this);
    $this->elementAfter = new InputValue($this);
    $this->setValue('');
  }

  public function getAttributeList() {
    return ['value', 'placeholder', 'onChange'];
  }

  public function setValue($value) {
    if ($value === false) {
      return;
    }
    $this->before = $value;
    $this->selected = '';
    $this->after = '';
    $this->elementBefore->setValue($this->before);
    $this->elementSelected->setValue($this->selected == '' ? ' ' : $this->selected);
    $this->elementAfter->setValue($this->after);
  }

  public function getValue() {
    return $this->before . $this->selected . $this->after;
  }

  public function setPlaceholder($value) {
    $this->placeholder = $value;
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

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->addClass('selected', true);
      if ($this->getValue() === '') {
        $this->elementBefore->setValue($this->placeholder);
        $this->elementBefore->addClass('placeholder', true);
      }
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->removeClass('selected', true);
    }
    if ($this->getValue() === '') {
      $this->elementBefore->setValue('');
      $this->elementBefore->removeClass('placeholder', true);
    }
    parent::removeClass($class, $dynamic);
  }

  private function refreshValue() {
    $this->elementBefore->setValue($this->before);
    $this->elementSelected->setValue($this->selected == '' ? ' ' : $this->selected);
    $this->elementAfter->setValue($this->after);
    $this->recalculateGeometry();
    $selected = $this->elementSelected;
    if ($selected->geometry->x + $selected->geometry->width > $this->scrollX + $this->geometry->width - $this->geometry->borderLeft) {
      $this->scrollX = $selected->geometry->x + $selected->geometry->width - $this->geometry->width + $this->geometry->borderLeft;
    } else if ($selected->geometry->x < $this->scrollX) {
      $this->scrollX = $selected->geometry->x;
    }
    Element::immediateRender($this);
    if ($this->onChange !== false) {
      call_user_func($this->onChange, $this);
    }
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        return true;
      case Action::DELETE_BACK:
        if (mb_strlen($this->selected) > 1) {
          $this->selected = '';
          if ($this->after !== '') {
            $this->selected = mb_substr($this->after, 0, 1);
            $this->after = mb_substr($this->after, 1);
          }
        } else {
          $this->before = mb_substr($this->before, 0, -1);
        }
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::DELETE_FORWARD:
        if ($this->after !== '') {
          $this->selected = mb_substr($this->after, 0, 1);
          $this->after = mb_substr($this->after, 1);
        } else {
          $this->selected = '';
        }
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::SELECT_LEFT:
        if ($this->selectDirection > 0) {
          if (mb_strlen($this->selected) > 1) {
            $this->after = mb_substr($this->selected, -1) . $this->after;
            $this->selected = mb_substr($this->selected, 0, -1);
          } else {
            $this->selectDirection = 0;
          }
        } else {
          if ($this->before !== '') {
            $this->selected = mb_substr($this->before, -1) . $this->selected;
            $this->before = mb_substr($this->before, 0, -1);
            $this->selectDirection = -1;
          }
        }
        $this->refreshValue();
        return true;
      case Action::MOVE_LEFT:
        if ($this->selected !== '') {
          $this->after = $this->selected . $this->after;
          $this->selected = '';
        }
        if ($this->before !== '') {
          $this->selected = mb_substr($this->before, -1);
          $this->before = mb_substr($this->before, 0, -1);
        } else if ($this->after !== '') {
          $this->selected = mb_substr($this->after, 0, 1);
          $this->after = mb_substr($this->after, 1);
        }
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::SELECT_RIGHT:
        if ($this->selectDirection < 0) {
          if (mb_strlen($this->selected) > 1) {
            $this->before .= mb_substr($this->selected, 0, 1);
            $this->selected = mb_substr($this->selected, 1);
          } else {
            $this->selectDirection = 0;
          }
        } else {
          if ($this->after !== '') {
            $this->selected .= mb_substr($this->after, 0, 1);
            $this->after = mb_substr($this->after, 1);
            $this->selectDirection = 1;
          }
        }
        $this->refreshValue();
        return true;
      case Action::MOVE_RIGHT:
        if ($this->selected !== '') {
          $this->before =  $this->before . $this->selected;
          $this->selected = '';
        }
        if ($this->after !== '') {
          $this->selected = mb_substr($this->after, 0, 1);
          $this->after = mb_substr($this->after, 1);
        }
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::SELECT_START:
        $this->selected = $this->before . $this->selected ;
        $this->before = '';
        $this->selectDirection = -1;
        $this->refreshValue();
        return true;
      case Action::MOVE_START:
        $this->after = $this->before . $this->selected . $this->after;
        $this->before = '';
        $this->selected = mb_substr($this->after, 0, 1);
        $this->after = mb_substr($this->after, 1);
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::SELECT_END:
        $this->selected = $this->selected . $this->after;
        $this->after = '';
        $this->selectDirection = 1;
        $this->refreshValue();
        return true;
      case Action::MOVE_END:
        $this->before = $this->before . $this->selected . $this->after;
        $this->after = '';
        $this->selected = '';
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case Action::CUT:
        Clipboard::set($this->selected);
        $this->selected = mb_substr($this->after, 0, 1);
        $this->after = mb_substr($this->after, 1);
        $this->refreshValue();
        return true;
      case Action::COPY:
        Clipboard::set($this->selected);
        $this->before .= $this->selected;
        $this->selected = mb_substr($this->after, 0, 1);
        $this->after = mb_substr($this->after, 1);
        $this->refreshValue();
        return true;
      case Action::PASTE:
        $paste = Clipboard::get();
        if ($paste !== false) {
          if (mb_strlen($this->selected) > 1) {
            $this->selected = $paste;
          } else {
            $this->after = $this->selected . $this->after;
            $this->selected = $paste;
          }
          $this->refreshValue();
        }
        return true;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
    if ($this->getValue() === '') {
      $this->elementBefore->removeClass('placeholder', true);
    }
    if (mb_strlen($this->selected) > 1) {
      $this->before .= $event['text'];
      $this->selected = mb_substr($this->after, 0, 1);
      $this->after = mb_substr($this->after, 1);
    } else {
      $this->before .= $event['text'];
    }
    $this->selectDirection = 0;
    $this->refreshValue();
    return true;
  }

}
