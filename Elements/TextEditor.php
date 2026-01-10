<?php

namespace SPTK;

class TextEditor extends Element {

  protected $lines = [];
  protected $cursor;
  protected $history;
  protected $lineHeight;
  protected $letterWidth;
  protected $tokenizer;
  protected $lineContexts = [];

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $fontSize);
    $this->letterWidth = $font->letterWidth;
    $this->lineHeight = $font->height;
    $this->cursor = new \SPTK\TextEditor\Cursor($this->lines);
    $this->history = new \SPTK\TextEditor\History($this->lines, $this->cursor);
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

  protected function lineSplice($offset, $length, $replacement) {
    $this->history->store($offset, $length, $replacement);
    array_splice($this->lines, $offset, $length, $replacement);
  }

  protected function clearSelection() {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[$row1], 0, $col1);
    $after = mb_substr($this->lines[$row2], $col2);
    $this->lineSplice($row1, $row2 - $row1 + 1, [$before . $after]);
  }

  protected function replaceSelection($newLines) {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    if ($row1 === $row2 && $col1 === $col2 - 1) {
      $col2 = $col1;
    }
    $before = mb_substr($this->lines[$row1], 0, $col1);
    $after = mb_substr($this->lines[$row2], $col2);
    $last = count($newLines) - 1;
    $newLines[0] = $before . $newLines[0];
    $newLines[$last] = $newLines[$last] . $after;
    $this->lineSplice($row1, $row2 - $row1 + 1, $newLines);
  }


  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      /* SPACE, NEW LINE */
      case Action::SELECT_ITEM:
        return true;
      case Action::DO_IT:
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        if ($row1 === $row2 && $col1 === $col2 - 1) {
          $col2 = $col1;
        }
        $before = mb_substr($this->lines[$row1], 0, $col1);
        $after = mb_substr($this->lines[$row2], $col2);
        $this->lineSplice($row1, $row2 - $row1 + 1, [$before, $after]);
        $this->cursor->modify($row1 + 1, 0, $row1 + 1, 0);
        break;
      /* UP */
      case Action::MOVE_UP:
        $this->cursor->moveUp();
        break;
      case Action::PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->cursor->movePageUp($linesOnScreen);
        break;
      case Action::LEVEL_UP:
        $this->cursor->moveDocStart();
        break;
      case Action::SELECT_UP:
        $this->cursor->moveUp(true);
        break;
      case Action::SELECT_PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->cursor->movePageUp($linesOnScreen, true);
        break;
      case Action::SELECT_LEVEL_UP:
        $this->cursor->moveDocStart(true);
        break;
      /* DOWN */
      case Action::MOVE_DOWN:
        $this->cursor->moveDown();
        break;
      case Action::PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->cursor->movePageDown($linesOnScreen);
        break;
      case Action::LEVEL_DOWN:
        $this->cursor->moveDocEnd();
        break;
      case Action::SELECT_DOWN:
        $this->cursor->moveDown(true);
        break;
      case Action::SELECT_PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->cursor->movePageDown($linesOnScreen, true);
        break;
      case Action::SELECT_LEVEL_DOWN:
        $this->cursor->moveDocEnd(true);
        break;
      /* LEFT */
      case Action::MOVE_LEFT:
        $this->cursor->moveBackward();
        break;
      case Action::MOVE_FIRST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->cursor->moveScreenStart($lettersOnScreen);
        break;
      case Action::MOVE_START:
        $this->cursor->moveLineStart();
        break;
      case Action::SELECT_LEFT:
        $this->cursor->moveBackward(true);
        break;
      case Action::SELECT_FIRST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->cursor->moveScreenStart($lettersOnScreen, true);
        break;
      case Action::SELECT_START:
        $this->cursor->moveLineStart(true);
        break;
      /* RIGHT */
      case Action::MOVE_RIGHT:
        $this->cursor->moveForward();
        break;
      case Action::MOVE_LAST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->cursor->moveScreenEnd($lettersOnScreen);
        break;
      case Action::MOVE_END:
        $this->cursor->moveLineEnd();
        break;
      case Action::SELECT_RIGHT:
        $this->cursor->moveForward(true);
        break;;
      case Action::SELECT_LAST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->cursor->moveScreenEnd($lettersOnScreen, true);
        break;
      case Action::SELECT_END:
        $this->cursor->moveLineEnd(true);
        break;
      /* DELETE */
      case Action::DELETE_BACK:
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $line = $this->lines[$row1];
        if ($row1 === $row2 && $col1 === 0 && $col2 === 1) {
          if ($row1 > 0) {
            $row1--;
            $line2 = $this->lines[$row1];
            $len = mb_strlen($line2);
            $this->cursor->modify($row1, $len, $row1, $len);
            $this->lineSplice($row1, 2, [$line2 . $line]);
          }
        } else if ($row1 === $row2 && $col1 === $col2 - 1) {
          $this->cursor->modify(false, $col1 - 1, false, $col1 - 1);
          $this->cursor->save();
          $this->clearSelection();
        } else {
          $this->cursor->modify($row1, $col1, $row1, $col1);
          $this->clearSelection();
        }
        break;
      case Action::DELETE_FORWARD:
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $line = $this->lines[$row1];
        $len = mb_strlen($line);
        if ($row1 === $row2 && $col1 === $len && $col2 === $len + 1) {
          $lcnt = count($this->lines);
          if ($row1 < $lcnt) {
            $line2 = $this->lines[$row1 + 1];
            $this->cursor->resetSelection();
            $this->lineSplice($row1, 2, [$line . $line2]);
          }
        } else {
          $this->cursor->modify($row1, $col1, $row1, $col1);
          $this->clearSelection();
        }
        break;
      /* COPY-PASTE */
      case Action::CUT:
        Clipboard::set($this->cursor->getSelection());
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $this->cursor->modify($row1, $col1, $row1, $col1);
        $this->clearSelection();
        break;
      case Action::COPY:
        Clipboard::set($this->cursor->getSelection());
        $this->cursor->resetSelection();
        break;
      case Action::PASTE:
        $paste = Clipboard::get();
        if ($paste !== false) {
          $lines = explode("\n", $paste);
          $n = count($lines);
          $len = mb_strlen(end($lines));
          $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
          if ($n === 1) {
            $this->cursor->modify($row1, $col1 + $len , $row1, $col1 + $len);
          } else {
            $this->cursor->modify($row1 + $n - 1, $len , $row1 + $n - 1, $len);
          }
          $this->replaceSelection($lines);
        }
        break;
      /* UNDO-REDO */
      case Action::UNDO:
        $this->history->undo();
        break;
      case Action::REDO:
        $this->history->redo();
        break;
      default:
        return false;
    }
    $this->update();
    return true;
  }

  public function textInputHandler($element, $event) {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $this->cursor->modify($row1, $col1 + 1, $row1, $col1 + 1);
    $this->replaceSelection([$event['text']]);
    $this->update();
    return true;
  }

}
