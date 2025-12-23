<?php

namespace SPTK;

class Input extends Element {

  private $before = '';
  private $selected = '';
  private $after = '';
  private $beforeR = '';
  private $selectedR = '';
  private $afterR = '';
  private $elementBefore;
  private $elementSelected;
  private $elementAfter;
  private $selectDirection = 0;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementBefore = new InputValue($this);
    $this->elementSelected = new InputValue($this);
    $this->elementAfter = new InputValue($this);
    $this->setValue('');
  }

  public function setValue($value) {
    $this->before = $this->beforeR = $value;
    $this->selected = $this->selectedR = '';
    $this->after = $this->afterR = '';
    $this->elementBefore->setValue($this->before);
    $this->elementSelected->setValue($this->selected == '' ? ' ' : $this->selected);
    $this->elementAfter->setValue($this->after);
  }

  public function getValue() {
    return $this->before . $this->selected . $this->after;
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->addClass('selected', true);
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->removeClass('selected', true);
    }
    parent::removeClass($class, $dynamic);
  }

  private function refreshValue() {
    $changed = false;
    if ($this->before !== $this->beforeR) {
      $this->elementBefore->setValue($this->before);
      $this->beforeR = $this->before;
      $changed = true;
    }
    if ($this->selected !== $this->selectedR) {
      $this->elementSelected->setValue($this->selected == '' ? ' ' : $this->selected);
      $this->selectedR = $this->selected;
      $changed = true;
    }
    if ($this->after !== $this->afterR) {
      $this->elementAfter->setValue($this->after);
      $this->afterR = $this->after;
      $changed = true;
    }
    if ($changed) {
      Element::immediateRender($this);
    }
  }

  public function keyPressHandler($element, $event) {
    if ($event['key'] == KeyCode::SPACE) {
      return true;
    }
    switch ($event['key']) {
      case KeyCode::BACKSPACE:
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
      case KeyCode::DELETE:
        if ($this->after !== '') {
          $this->selected = mb_substr($this->after, 0, 1);
          $this->after = mb_substr($this->after, 1);
        } else {
          $this->selected = '';
        }
        $this->selectDirection = 0;
        $this->refreshValue();
        return true;
      case KeyCode::LEFT:
        if ($event['mod'] & KeyModifier::SHIFT) {
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
        } else if ($event['mod'] == 0) {
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
        } else {
          break;
        }
        $this->refreshValue();
        return true;
      case KeyCode::RIGHT:
        if ($event['mod'] & KeyModifier::SHIFT) {
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
        } else if ($event['mod'] == 0) {
          if ($this->selected !== '') {
            $this->before =  $this->before . $this->selected;
            $this->selected = '';
          }
          if ($this->after !== '') {
            $this->selected = mb_substr($this->after, 0, 1);
            $this->after = mb_substr($this->after, 1);
          }
          $this->selectDirection = 0;
        } else {
          break;
        }
        $this->refreshValue();
        return true;
      case KeyCode::HOME: // todo: KP_HOME?
        if ($event['mod'] & KeyModifier::SHIFT) {
          $this->selected = $this->before . $this->selected ;
          $this->before = '';
          $this->selectDirection = -1;
        } else {
          $this->after = $this->before . $this->selected . $this->after;
          $this->before = '';
          $this->selected = mb_substr($this->after, 0, 1);
          $this->after = mb_substr($this->after, 1);
          $this->selectDirection = 0;
        }
        $this->refreshValue();
        return true;
      case KeyCode::END: // todo: KP_END?
        if ($event['mod'] & KeyModifier::SHIFT) {
          $this->selected = $this->selected . $this->after;
          $this->after = '';
          $this->selectDirection = 1;
        } else {
          $this->before = $this->before . $this->selected . $this->after;
          $this->after = '';
          $this->selected = '';
          $this->selectDirection = 0;
        }
        $this->refreshValue();
        return true;
    }
    $paste = false;
    $clipboardAction = Clipboard::processEvent($event, $this->selected, $paste);
    if ($clipboardAction == Clipboard::CUT) {
      $this->selected = mb_substr($this->after, 0, 1);
      $this->after = mb_substr($this->after, 1);
      $this->refreshValue();
      return true;
    } else if ($clipboardAction == Clipboard::COPY) {
      $this->before .= $this->selected;
      $this->selected = mb_substr($this->after, 0, 1);
      $this->after = mb_substr($this->after, 1);
      $this->refreshValue();
      return true;
    } else if ($clipboardAction == Clipboard::PASTE) {
      if (mb_strlen($this->selected) > 1) {
        $this->selected = $paste;
      } else {
        $this->after = $this->selected . $this->after;
        $this->selected = $paste;
      }
      $this->refreshValue();
      return true;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
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
