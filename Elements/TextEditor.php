<?php

namespace SPTK;

class TextEditor extends Element {

  protected $lines = [];
  protected $row1 = 0;
  protected $col1 = 0;
  protected $row2 = 0;
  protected $col2 = 1;
  protected $tokenizer;
  protected $tokens = [];

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
  }

  public function getAttributeList() {
    return ['tokenizer'];
  }

  public function setTokenizer($value) {
    if ($value === 'false' || $value === false) {
      $value = '\SPTK\Tokenizer';
    }
    $this->tokenizer = $value;
  }

  public function setValue($value) {
    $this->lines = explode("\n", $value);
    $this->tokenize();
    $this->buildTree();
  }

  protected function tokenize() {
    $this->tokens = Tokenizer::start($this->lines, $this->tokenizer);
  }

  protected function splitToken($token, $split, $selected) {
echo "split token: {$token['value']} ({$token['length']})\n";
    $style = $token['style'];
    if ($selected) {
      $style .= ' InputValue:selected';
    }
    $iv = new InputValue($this, false, $style);
    $iv->setValue(mb_substr($token['value'], 0, $split));
echo "  to: " . mb_substr($token['value'], 0, $split) . " \n";
    $token['value'] = mb_substr($token['value'], $split);
echo "  and: " . $token['value'] . " \n";
    $token['length'] -= $split;
echo "  newlen: {$token['length']}\n";
    return $token;
  }

  protected function buildTree() {
    $this->clear();
    $selected = false;
    foreach ($this->tokens as $i => $line) {
      $row = new Element($this, false, false, 'InputRow');
      $j = 0;
      foreach ($line['tokens'] as $token) {
        if ($i === $this->row1 && $this->col1 > $j && $this->col1 < $j + $token['length']) {
          $split = $this->col1 - $j;
          $token = $this->splitToken($token, $split, $selected);
          $j += $split;
        }
        if ($i === $this->row1 && $j === $this->col1) {
          $selected = true;
        }
        if ($i === $this->row2 && $this->col2 > $j && $this->col2 < $j + $token['length']) {
          $split = $this->col2 - $j;
          $token = $this->splitToken($token, $split, $selected);
          $j += $split;
        }
        if ($i === $this->row2 && $j === $this->col2) {
          $selected = false;
        }
        if ($selected) {
          $token['style'] .= ' InputValue:selected';
        }
        $iv = new InputValue($this, false, $token['style']);
        $iv->setValue($token['value']);
        $j += $token['length'];
      }
      new Element($this, false, false, 'NL');
    }
  }

  protected function update() {
$t = microtime(true);
    $this->tokenize();
echo 'tokenize: ', microtime(true) - $t, "\n";
$t = microtime(true);
    $this->buildTree();
echo 'tree: ', microtime(true) - $t, "\n";
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        return true;
      case Action::DELETE_BACK:
        return true;
      case Action::DELETE_FORWARD:
        return true;
      case Action::SELECT_LEFT:
        return true;
      case Action::SELECT_RIGHT:
        return true;
      case Action::MOVE_LEFT:
        $this->col1--;
        $this->col2--;
        $this->update();
        Element::refresh();
        return true;
      case Action::MOVE_RIGHT:
        $this->col1++;
        $this->col2++;
        $this->update();
        Element::refresh();
        return true;
      case Action::MOVE_UP:
        $this->row1--;
        $this->row2--;
        $this->update();
        Element::refresh();
        return true;
      case Action::MOVE_DOWN:
        $this->row1++;
        $this->row2++;
        $this->update();
        Element::refresh();
        return true;
      case Action::SELECT_START:
        return true;
      case Action::MOVE_START:
        return true;
      case Action::SELECT_END:
        return true;
      case Action::MOVE_END:
        return true;
      case Action::CUT:
        return true;
      case Action::COPY:
        return true;
      case Action::PASTE:
        return true;
    }
    return false;
  }

  public function textInputHandler($element, $event) {
    if ($this->row1 != $this->row2 || $this->col2 - $this->col1 > 1) {
    } else {
      $cline = $this->lines[$this->row1];
      $this->lines[$this->row1] = mb_substr($cline, 0, $this->col1) . $event['text'] . mb_substr($cline, $this->col1);
      $this->col1++;
      $this->col2++;
    }
    $this->selectDirection = 0;
    $this->update();
    Element::refresh();
    return true;
  }

}
