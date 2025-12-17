<?php

namespace SPTK;

class Panel extends Element {

  private $inputList;
  private $focusIndex;
  private $hotKeys = [];

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->focusIndex = -1;
  }

  public function show() {
    $this->display = true;
    $this->inputList = [];
    $this->setInputList($this);
    if (empty($this->inputList)) {
      $this->focusIndex = -1;
      $this->raise();
    } else {
      if ($this->focusIndex < 0 || $this->focusIndex >= count($this->inputList)) {
        $this->focusIndex = 0;
      }
      $focusedElement = $this->inputList[$this->focusIndex]['element'];
      $focusedElement->raise();
      $focusedElement->addClass('active', true);
    }
  }

  public function getValue() {
    $value = [];
    foreach ($this->inputList as $input) {
      $key = $input['element']->getName();
      if (is_string($key)) {
        $value[$key] = $input['element']->getValue();
      }
    }
    return $value;
  }

  public function setValue($values) {
    foreach ($values as $name => $value) {
      $element = Element::byName($name, $this);
      $element->setValue($value);
    }
  }

  private function setInputList($element) {
    if ($element->acceptInput) {
      $this->inputList[] = $this->getInputElementDetails($element);
      return;
    }
    foreach ($element->descendants as $descendant) {
      $this->setInputList($descendant);
    }
  }

  private function getInputElementDetails($element) {
    $details = [];
    $details['id'] = $element->id;
    $details['element'] = $element;
    $x = 0;
    $y = 0;
    self::getRelativePos($this->id, $element, $x, $y);
    $details['x1'] = $x;
    $details['y1'] = $y;
    $details['x2'] = $x + $element->geometry->width;
    $details['y2'] = $y + $element->geometry->height;
    $details['xm'] = $x + $element->geometry->width / 2;
    $details['ym'] = $y + $element->geometry->height / 2;
    return $details;
  }

  public function hide() {
    $this->display = false;
    $this->lower();
  }

  public function activateInput() {
    $element = $this->inputList[$this->focusIndex]['element'];
    $element->addClass('active', true);
    $element->raise();
  }

  public function inactivateInput() {
    $this->inputList[$this->focusIndex]['element']->removeClass('active', true);
  }

  public function addHotKey($key, $callback) {
    $this->hotKeys[$key] = $callback;
  }

  public function removeHotKey($key) {
    unset($this->hotKeys[$key]);
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    if (isset($this->hotKeys[$event['key']])) {
      call_user_func($this->hotKeys[$event['key']], $this);
      return true;
    }
    switch ($event['key']) {
      case KeyCode::ESCAPE:
        $this->inputList = [];
        $this->hide();
        Element::refresh();
        return true;
      case KeyCode::TAB:
        if ($this->focusIndex < 0) {
          return true;
        }
        if ($event['mod'] == 0) {
          $this->inactivateInput();
          $this->focusIndex++;
          if ($this->focusIndex >= count($this->inputList)) {
            $this->focusIndex = 0;
          }
          $this->activateInput();
          Element::refresh();
        } else if (($event['mod'] | KeyModifier::SHIFT) > 0) {
          $this->inactivateInput();
          $this->focusIndex--;
          if ($this->focusIndex < 0) {
            $this->focusIndex = count($this->inputList) - 1;
          }
          $this->activateInput();
          Element::refresh();
        }
        return true;
      case KeyCode::LEFT:
        $this->activateClosestInput('left');
        return true;
      case KeyCode::RIGHT:
        $this->activateClosestInput('right');
        return true;
      case KeyCode::UP:
        $this->activateClosestInput('up');
        return true;
      case KeyCode::DOWN:
        $this->activateClosestInput('down');
        return true;
    }
    return true;
  }

  private function findClosestInput($direction) {
    $focus = $this->inputList[$this->focusIndex];
    $bestPrimary = PHP_INT_MAX;
    $bestSecondary = PHP_INT_MAX;
    $bestIdx = false;
    foreach ($this->inputList as $i => $input) {
      if ($input['id'] === $focus['id']) {
        continue;
      }
      $valid = false;
      switch ($direction) {
        case 'left':
          $valid = $input['x2'] < $focus['x1'];
          $primary = abs($input['ym'] - $focus['ym']);
          $secondary = abs($input['x2'] - $focus['x1']);
          break;
        case 'right':
          $valid = $input['x1'] > $focus['x2'];
          $primary = abs($input['ym'] - $focus['ym']);
          $secondary = abs($input['x1'] - $focus['x2']);
          break;
        case 'up':
          $valid = $input['y2'] < $focus['y1'];
          $primary = abs($input['xm'] - $focus['xm']);
          $secondary = abs($input['y2'] - $focus['y1']);
          break;
        case 'down':
          $valid = $input['y1'] > $focus['y2'];
          $primary = abs($input['xm'] - $focus['xm']);
          $secondary = abs($input['y1'] - $focus['y2']);
          break;
        default:
          throw new \Exception("Invalid direction: {$direction}");
      }
      if ($valid && ($primary < $bestPrimary || ($primary == $bestPrimary && $secondary < $bestSecondary))) {
        $bestPrimary = $primary;
        $bestSecondary = $secondary;
        $bestIdx = $i;
      }
    }
    return $bestIdx;
  }

 private function findFurthestInput($direction) {
    $focus = $this->inputList[$this->focusIndex];
    $bestPrimary = PHP_INT_MAX;
    $bestSecondary = 0;
    $bestIdx = false;
    foreach ($this->inputList as $i => $input) {
      if ($input['id'] === $focus['id']) {
        continue;
      }
      $valid = false;
      switch ($direction) {
        case 'left':
          $valid = $input['x2'] > $focus['x1'];
          $primary = abs($input['ym'] - $focus['ym']);
          $secondary = abs($input['x2'] - $focus['x1']);
          break;
        case 'right':
          $valid = $input['x1'] < $focus['x2'];
          $primary = abs($input['ym'] - $focus['ym']);
          $secondary = abs($input['x1'] - $focus['x2']);
          break;
        case 'up':
          $valid = $input['y1'] > $focus['y2'];
          $primary = abs($input['xm'] - $focus['xm']);
          $secondary = abs($input['y1'] - $focus['y2']);
          break;
        case 'down':
          $valid = $input['y2'] < $focus['y1'];
          $primary = abs($input['xm'] - $focus['xm']);
          $secondary = abs($input['y2'] - $focus['y1']);
          break;
        default:
          throw new \Exception("Invalid direction: {$direction}");
      }
      if ($valid && ($primary < $bestPrimary || ($primary == $bestPrimary && $secondary > $bestSecondary))) {
        $bestPrimary = $primary;
        $bestSecondary = $secondary;
        $bestIdx = $i;
      }
    }
    return $bestIdx;

 }

  private function activateClosestInput($direction) {
    $this->inactivateInput();
    $idx = $this->findClosestInput($direction);
    if ($idx === false) {
      $idx = $this->findFurthestInput($direction);
    }
    if ($idx !== false) {
      $this->focusIndex = $idx;
    }
    $this->activateInput();
    Element::refresh();
  }

}
