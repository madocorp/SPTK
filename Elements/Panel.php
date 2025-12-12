<?php

namespace SPTK;

class Panel extends Element {

  private $inputList;
  private $focusId;
  private $hotKeys = [];

  protected function init() {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->focusId = -1;
  }

  public function show() {
    $this->display = true;
    $this->inputList = [];
    $this->setInputList($this);
    if (empty($this->inputList)) {
      $this->focusId = -1;
      $this->raise();
    } else {
      if ($this->focusId < 0 || $this->focusId >= count($this->inputList)) {
        $this->focusId = 0;
      }
      $focusedElement = $this->inputList[$this->focusId]['element'];
      $focusedElement->raise();
      $focusedElement->addClass('active', true);
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
    $details['iid'] = $element->iid;
    $details['element'] = $element;
    $x = 0;
    $y = 0;
    self::getRelativePos($this->iid, $element, $x, $y);
    $details['x'] = $x;
    $details['y'] = $y;
    return $details;
  }

  public function hide() {
    $this->display = false;
    $this->lower();
  }

  public function activateInput() {
    $element = $this->inputList[$this->focusId]['element'];
    $element->addClass('active', true);
    $element->raise();
  }

  public function inactivateInput() {
    $this->inputList[$this->focusId]['element']->removeClass('active', true);
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
    if ($event['key'] == KeyCode::ESCAPE) {
      $this->inputList = [];
      $this->hide();
      Element::refresh();
      return true;
    }
    if ($event['key'] == KeyCode::TAB) {
      if ($this->focusId < 0) {
        return true;
      }
      if ($event['mod'] == 0) {
        $this->inactivateInput();
        $this->focusId++;
        if ($this->focusId >= count($this->inputList)) {
          $this->focusId = 0;
        }
        $this->activateInput();
        Element::refresh();
      } else if (($event['mod'] | KeyModifier::SHIFT) > 0) {
        $this->inactivateInput();
        $this->focusId--;
        if ($this->focusId < 0) {
          $this->focusId = count($this->inputList) - 1;
        }
        $this->activateInput();
        Element::refresh();
      }
      return true;
    }
    return true;
  }

}
