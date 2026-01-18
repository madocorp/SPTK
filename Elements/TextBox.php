<?php

namespace SPTK;

class TextBox extends Element {

  protected $lines = [''];
  protected $cursor;
  protected $lineHeight;
  protected $letterWidth;
  protected $tokenizer;
  protected $lineContexts = [];

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $fontSize);
    $this->letterWidth = $font->letterWidth;
    $this->lineHeight = $font->height;
    $this->cursor = new \SPTK\TextEditor\Cursor($this->lines);
  }

  public function getAttributeList() {
    return ['tokenizer', 'file'];
  }

  public function setTokenizer($value) {
    if ($value === 'false' || $value === false) {
      $value = '\SPTK\Tokenizer';
    }
    $this->tokenizer = $value;
  }

  public function setFile($file) {
    if ($file === false) {
      return;
    }
    if (strpos($file, '/') !== 0) {
      if (defined('APP_PATH')) {
        $dir = dirname(APP_PATH);
        $file = "{$dir}/{$file}";
      } else {
        $file = getcwd() . '/' . $file;
      }
    }
    if (!file_exists($file)) {
      return;
    }
    $content = file_get_contents($file);
    if ($content === false) {
      return;
    }
    $this->setValue($content);
  }

  public function setValue($value) {
    $this->lines = explode("\n", $value);
    $this->measure();
    $this->update();
  }

  protected function calculateWidths() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateWidths();
    }
  }

  protected function calculateHeights() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $maxY = count($this->lines) * $this->lineHeight;
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent, $maxY);
  }

  protected function layout() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
    $maxLen = 0;
    foreach ($this->lines as $line) {
      $maxLen = max($maxLen, mb_strlen($line));
    }
    $this->geometry->contentWidth = $maxLen * $this->letterWidth;
  }

  protected function tokenize($from, $to) {
    $context = $this->tokenizer;
    $tokenizeFrom = count($this->lineContexts);
    if ($from < $tokenizeFrom) {
      $tokenizeFrom = $from;
    }
    if ($tokenizeFrom > 0) {
      $context = $this->lineContexts[$tokenizeFrom - 1];
    }
    $lines = array_slice($this->lines, $tokenizeFrom, $to - $tokenizeFrom);
    $tokens = Tokenizer::start($lines, $context);
    $result = [];
    for ($i = $tokenizeFrom; $i < $to; $i++) {
      $lineTokens = array_shift($tokens);
      $this->lineContexts[$i] = $lineTokens['context'];
      if ($i >= $from) {
        $result[$i] = $lineTokens['tokens'];
      }
    }
    return $result;
  }

  protected function splitToken($token, $split, $selected, $row) {
    $style = $token['style'];
    if ($selected) {
      $style .= ' InputValue:selected';
    }
    $iv = new InputValue($row, false, $style);
    $iv->setValue(mb_substr($token['value'], 0, $split));
    $token['value'] = mb_substr($token['value'], $split);
    $token['length'] -= $split;
    return $token;
  }

  protected function buildTree($firstOnScreen, $tokens) {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $this->clear();
    $selected = ($row1 < $firstOnScreen && $row2 >= $firstOnScreen);
    foreach ($tokens as $i => $line) {
      $row = new InputRow($this);
      $row->setPos($i, $this->lineHeight);
      $j = 0;
      foreach ($line as $token) {
        if ($i === $row1 && $col1 > $j && $col1 < $j + $token['length']) {
          $split = $col1 - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $row1 && $j === $col1) {
          $selected = true;
        }
        if ($i === $row2 && $col2 > $j && $col2 < $j + $token['length']) {
          $split = $col2 - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $row2 && $j === $col2) {
          $selected = false;
        }
        if ($selected) {
          $token['style'] .= ' InputValue:selected';
        }
        $iv = new InputValue($row, false, $token['style']);
        $iv->setValue($token['value']);
        $j += $token['length'];
      }
      if ($row1 === $i && $col1 === $j) {
        $selected = true;
      }
      if ($row2 === $i && $col2 === $j) {
        $selected = false;
      }
      $style = false;
      if ($selected) {
        $style = 'InputValue:selected';
        if ($row1 != $row2 || $row1 < $row2 - 1) {
          $style = 'InputValue:newline';
        }
      }
      $iv = new InputValue($row, false, $style);
      $iv->setValue(' ');
      $j++;
      if ($row2 === $i && $col2 === $j) {
        $selected = false;
      }
    }
  }

  protected function setScroll() {
    $cursor = $this->cursor->get();
    $row = $cursor[0];
    $col = $cursor[1];
    if ($row === 0) {
      $this->scrollY = 0;
    } else {
      $ryBottom = ($row + 1) * $this->lineHeight;
      $ryTop = $row * $this->lineHeight;
      if ($ryBottom > $this->scrollY + $this->geometry->innerHeight) {
        $this->scrollY = $ryBottom - $this->geometry->innerHeight;
      } else if ($ryTop < $this->scrollY) {
        $this->scrollY = $ryTop;
      }
    }
    if ($col === 0) {
      $this->scrollX = 0;
    } else {
      $rxLeft = $col * $this->letterWidth;
      $rxRight = ($col + 1) * $this->letterWidth;
      if ($rxRight > $this->scrollX + $this->geometry->innerWidth) {
        $this->scrollX = $rxRight - $this->geometry->innerWidth;
      } else if ($rxLeft < $this->scrollX) {
        $this->scrollX = $rxLeft;
      }
    }
  }

  protected function update() {
    $this->cursor->save();
    $this->setScroll();
    if ($this->geometry->height == 0) {
      $firstOnScreen = 0;
      $lastOnScreen = min(300, count($this->lines));
    } else {
      $firstOnScreen = max(0, (int)(($this->scrollY + $this->geometry->paddingTop) / $this->lineHeight) - 1);
      $lastOnScreen = min($firstOnScreen + (int)($this->geometry->height / $this->lineHeight) + 1, count($this->lines));
    }
    $tokens = $this->tokenize($firstOnScreen, $lastOnScreen);
    $this->buildTree($firstOnScreen, $tokens);
    Element::immediateRender($this);
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
    $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
    $handled = $this->cursor->handleKeys($keycombo, $linesOnScreen, $lettersOnScreen);
    if (!$handled) {
      switch ($keycombo) {
        /* COPY */
        case Action::COPY:
          Clipboard::set($this->cursor->getSelection());
          $this->cursor->resetSelection();
          break;
        default:
          return false;
      }
    }
    $this->update();
    return true;
  }

}
