<?php

namespace SPTK;

class TextEditor extends Element {

  protected $lines = [];
  protected $row1 = 0;
  protected $col1 = 0;
  protected $row2 = 0;
  protected $col2 = 1;
  protected $selectDirection = 0;
  protected $lineHeight;
  protected $letterWidth;
  protected $tokenizer;
  protected $lineContexts = [];
  protected $lineUnderConstruction = false;
  protected $originalLine = false;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $fontSize);
    $this->letterWidth = $font->letterWidth;
    $this->lineHeight = $font->height;
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
    $this->clear();
    $selected = ($this->row1 < $firstOnScreen && $this->row2 >= $firstOnScreen);
    foreach ($tokens as $i => $line) {
      $row = new InputRow($this);
      $row->setPos($i, $this->lineHeight);
      $j = 0;
      foreach ($line as $token) {
        if ($i === $this->row1 && $this->col1 > $j && $this->col1 < $j + $token['length']) {
          $split = $this->col1 - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $this->row1 && $j === $this->col1) {
          $selected = true;
        }
        if ($i === $this->row2 && $this->col2 > $j && $this->col2 < $j + $token['length']) {
          $split = $this->col2 - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $this->row2 && $j === $this->col2) {
          $selected = false;
        }
        if ($selected) {
          $token['style'] .= ' InputValue:selected';
        }
        $iv = new InputValue($row, false, $token['style']);
        $iv->setValue($token['value']);
        $j += $token['length'];
      }
      if ($this->row2 === $i && $this->col2 == $j) {
       $selected = false;
      }
      if ($this->row2 === $i && $this->col2 > $j) {
        $iv = new InputValue($row, false, 'InputValue:selected');
        $iv->setValue(' ');
      }
    }
  }

  protected function setScroll() {
    if ($this->selectDirection > 0) {
      $row = $this->row2;
      $col = $this->col2;
    } else {
      $row = $this->row1;
      $col = $this->col1;
    }
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
$t = microtime(true);
    $this->setScroll();
    if ($this->geometry->height == 0) {
      $firstOnScreen = 0;
      $lastOnScreen = min(300, count($this->lines));
    } else {
      $firstOnScreen = max(0, (int)(($this->scrollY + $this->geometry->paddingTop) / $this->lineHeight) - 1);
      $lastOnScreen = min($firstOnScreen + (int)($this->geometry->height / $this->lineHeight) + 1, count($this->lines));
    }
    $tokens = $this->tokenize($firstOnScreen, $lastOnScreen);
echo 'tokenize: ', microtime(true) - $t, "\n";
$t = microtime(true);
    $this->buildTree($firstOnScreen, $tokens);
echo 'tree: ', microtime(true) - $t, "\n";
    Element::refresh();
  }

  protected function lineSplice($offset, $length, $replacement) {
    // fill undo stack
    array_splice($this->lines, $offset, $length, $replacement);
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        return true;
      case Action::SELECT_LEFT:
        return true;
      case Action::SELECT_RIGHT:
        return true;
      case Action::SELECT_UP:
        return true;
      case Action::SELECT_DOWN:
        return true;
      case Action::SELECT_START:
        return true;
      case Action::SELECT_END:
        return true;
      case Action::MOVE_LEFT:
        $this->col1--;
        if ($this->col1 < 0) {
          $this->row1--;
          if ($this->row1 < 0) {
            $this->row1 = 0;
            $this->col1 = 0;
          } else {
            $this->col1 = mb_strlen($this->lines[$this->row1]);
          }
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::MOVE_RIGHT:
        $this->col1++;
        $len = mb_strlen($this->lines[$this->row1]);
        if ($this->col1 > $len) {
          $this->row1++;
          $lcnt = count($this->lines);
          if ($this->row1 >= $lcnt) {
            $this->row1 = $lcnt - 1;
            $this->col1 = $len;
          } else {
            $this->col1 = 0;
          }
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::MOVE_UP:
        $this->row1--;
        if ($this->row1 < 0) {
          $this->row1 = 0;
        }
        $len = mb_strlen($this->lines[$this->row1]);
        if ($this->col1 > $len) {
          $this->col1 = $len;
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::MOVE_DOWN:
        $this->row1++;
        $lcnt = count($this->lines);
        if ($this->row1 >= $lcnt) {
          $this->row1 = $lcnt - 1;
        }
        $len = mb_strlen($this->lines[$this->row1]);
        if ($this->col1 > $len) {
          $this->col1 = $len;
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::MOVE_START:
        $this->col1 = 0;
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::MOVE_END:
        $this->col1 = mb_strlen($this->lines[$this->row1]);
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row1 += $linesOnScreen;
        $lcnt = count($this->lines);
        if ($this->row1 >= $lcnt) {
          $this->row1 = $lcnt - 1;
        }
        $len = mb_strlen($this->lines[$this->row1]);
        if ($this->col1 > $len) {
          $this->col1 = $len;
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row1 -= $linesOnScreen;
        if ($this->row1 < 0) {
          $this->row1 = 0;
        }
        $len = mb_strlen($this->lines[$this->row1]);
        if ($this->col1 > $len) {
          $this->col1 = $len;
        }
        $this->row2 = $this->row1;
        $this->col2 = $this->col1 + 1;
        $this->update();
        return true;
      case Action::DELETE_BACK:
        return true;
      case Action::DELETE_FORWARD:
        $line = $this->lines[$this->row1];
        if ($this->col1 === mb_strlen($line)) {
          $lcnt = count($this->lines);
          if ($this->row1 < $lcnt) {
            $line2 = $this->lines[$this->row1 + 1];
            $this->lineSplice($this->row1, 2, $line . $line2);
          }
        } else {
          $line = mb_substr($line, 0, $this->col1) . mb_substr($line, $this->col1 + 1);
          $this->lineSplice($this->row1, 1, $line);
        }
        $this->update();
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
    return true;
  }

}
