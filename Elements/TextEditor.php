<?php

namespace SPTK;

class TextEditor extends Element {

  protected $lines = [];
  protected $caret = [0, 0];
  protected $anchor = [0, 0];
  protected $newcaret;
  protected $newanchor;
  protected $lineHeight;
  protected $letterWidth;
  protected $tokenizer;
  protected $lineContexts = [];
  protected $lineUnderConstruction = false;
  protected $originalLine = false;
  protected $originalCursor = false;
  protected $undo = [];
  protected $redo = [];

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

  protected function selectionToCoordinates(&$row1, &$col1, &$row2, &$col2) {
    if ($this->caret[0] == $this->anchor[0] && $this->caret[1] == $this->anchor[1]) {
      $row1 = $this->caret[0];
      $col1 = $this->caret[1];
      $row2 = $this->anchor[0];
      $col2 = $this->anchor[1] + 1;
    } else if (
      $this->caret[0] < $this->anchor[0] ||
      ($this->caret[0] == $this->anchor[0] && $this->caret[1] < $this->anchor[1])
    ) {
      $row1 = $this->caret[0];
      $col1 = $this->caret[1];
      $row2 = $this->anchor[0];
      $col2 = $this->anchor[1] + 1;
    } else {
      $row1 = $this->anchor[0];
      $col1 = $this->anchor[1];
      $row2 = $this->caret[0];
      $col2 = $this->caret[1] + 1;
    }
  }

  protected function buildTree($firstOnScreen, $tokens) {
    $this->selectionToCoordinates($row1, $col1, $row2, $col2);
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
    $row = $this->caret[0];
    $col = $this->caret[1];
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

  protected function lineSplice($offset, $length, $replacement, $undo = true) {
    if ($undo) {
      $save = true;
      if ($length === 1 && count($replacement) === 1) {
        if ($this->lineUnderConstruction === false) {
          $this->originalLine = $this->lines[$offset];
          $this->originalCursor = [$this->caret, $this->anchor];
          $this->lineUnderConstruction = $offset;
        } else if ($this->lineUnderConstruction !== $offset) {
          $this->undo[] = [$this->lineUnderConstruction, [$this->originalLine], [$this->lines[$this->lineUnderConstruction]], $this->originalCursor, [$this->newcaret, $this->newanchor]];
          $this->originalLine = $this->lines[$offset];
          $this->originalCursor = [$this->caret, $this->anchor];
          $this->lineUnderConstruction = $offset;
        }
        $save = false;
      } else if ($this->lineUnderConstruction !== false) {
        $this->undo[] = [$this->lineUnderConstruction, [$this->originalLine], [$this->lines[$this->lineUnderConstruction]], $this->originalCursor, [$this->newcaret, $this->newanchor]];
        $this->originalLine = false;
        $this->originalCursor = false;
        $this->lineUnderConstruction = false;
      }
      $this->redo = [];
      if ($save) {
        $original = array_slice($this->lines, $offset, $length);
        $this->undo[] = [$offset, $original, $replacement, [$this->caret, $this->anchor], [$this->newcaret, $this->newanchor]];
      }
    }
echo "=== UNDO ===\n";
foreach ($this->undo as $undo) {
  echo "--- {$undo[0]} ---\n";
  echo "<<< ", implode(' | ', $undo[1]), "\n";
  echo ">>> ", implode(' | ', $undo[2]), "\n";
}
echo "under construction: {$this->lineUnderConstruction} ({$this->originalLine})\n";
    array_splice($this->lines, $offset, $length, $replacement);
  }

  protected function checkDocStart() {
    $this->newcaret[0] = max(0, $this->newcaret[0]);
  }

  protected function checkDocEnd() {
    $lcnt = count($this->lines);
    $this->newcaret[0] = min($lcnt - 1, $this->newcaret[0]);
  }

  protected function checkLineLength() {
    $len = mb_strlen($this->lines[$this->newcaret[0]]);
    $this->newcaret[1] = min($len, $this->newcaret[1]);
  }

  protected function moveForward() {
    $len = mb_strlen($this->lines[$this->newcaret[0]]);
    if ($this->newcaret[1] < $len) {
      $this->newcaret[1]++;
    } else {
      $lcnt = count($this->lines);
      if ($this->newcaret[0] < $lcnt - 1) {
        $this->newcaret[0]++;
        $this->newcaret[1] = 0;
      }
    }
  }

  protected function moveBackward() {
    if ($this->newcaret[1] > 0) {
      $this->newcaret[1]--;
    } else {
      if ($this->newcaret[0] > 0) {
        $this->newcaret[0]--;
        $this->newcaret[1] = mb_strlen($this->lines[$this->newcaret[0]]);
      }
    }
  }

  protected function resetSelection() {
    $this->newanchor[0] = $this->newcaret[0];
    $this->newanchor[1] = $this->newcaret[1];
  }

  protected function getSelection() {
    $this->selectionToCoordinates($row1, $col1, $row2, $col2);
    $lines = [];
    for ($i = $row1; $i <= $row2; $i++) {
      $line = $this->lines[$i];
      if ($i === $row2) {
        $line = mb_substr($line, 0, $col2);
      }
      if ($i === $row1) {
        $line = mb_substr($line, $col1);
      }
      $lines[] = $line;
    }
    return implode("\n", $lines);
  }

  protected function replaceSelection($newLines, $allowInsert = true) {
    $this->selectionToCoordinates($row1, $col1, $row2, $col2);
    if ($allowInsert && $row1 === $row2 && $col1 === $col2 - 1) {
      $col2 = $col1;
    }
    $before = mb_substr($this->lines[$row1], 0, $col1);
    $after = mb_substr($this->lines[$row2], $col2);
    $last = count($newLines) - 1;
    $newLines[0] = $before . $newLines[0];
    $newLines[$last] = $newLines[$last] . $after;
    $this->lineSplice($row1, $row2 - $row1 + 1, $newLines);
  }

  protected function initNewCursor() {
    $this->newcaret = $this->caret;
    $this->newanchor = $this->anchor;
  }

  protected function saveCursor() {
    $this->caret = $this->newcaret;
    $this->anchor = $this->newanchor;
  }

  public function keyPressHandler($element, $event) {
    $this->initNewCursor();
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      /* SPACE, NEW LINE */
      case Action::SELECT_ITEM:
        return true;
      case Action::DO_IT:
        $this->selectionToCoordinates($row1, $col1, $row2, $col2);
        $this->replaceSelection(['', '']);
        $this->newcaret[0] = $row1 + 1;
        $this->newcaret[1] = 0;
        $this->resetSelection();
        break;
      /* UP */
      case Action::MOVE_UP:
        $this->newcaret[0]--;
        $this->checkDocStart();
        $this->checkLineLength();
        $this->resetSelection();
        break;
      case Action::PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->newcaret[0] -= $linesOnScreen;
        $this->checkDocStart();
        $this->checkLineLength();
        $this->resetSelection();
        break;
      case Action::LEVEL_UP:
        $this->newcaret[0] = 0;
        $this->newcaret[1] = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_UP:
        $this->newcaret[0]--;
        $this->checkDocStart();
        $this->checkLineLength();
        break;
      case Action::SELECT_PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->newcaret[0] -= $linesOnScreen;
        $this->checkDocStart();
        $this->checkLineLength();
        break;
      case Action::SELECT_LEVEL_UP:
        $this->newcaret[0] = 0;
        $this->newcaret[1] = 0;
        break;
      /* DOWN */
      case Action::MOVE_DOWN:
        $this->newcaret[0]++;
        $this->checkDocEnd();
        $this->checkLineLength();
        $this->resetSelection();
        break;
      case Action::PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->newcaret[0] += $linesOnScreen;
        $this->checkDocEnd();
        $this->checkLineLength();
        $this->resetSelection();
        break;
      case Action::LEVEL_DOWN:
        $lines = count($this->lines) - 1;
        $this->newcaret[0] = $lines;
        $this->newcaret[1] = mb_strlen($this->lines[$lines]);
        $this->resetSelection();
        break;
      case Action::SELECT_DOWN:
        $this->newcaret[0]++;
        $this->checkDocEnd();
        $this->checkLineLength();
        break;
      case Action::SELECT_PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->newcaret[0] += $linesOnScreen;
        $this->checkDocEnd();
        $this->checkLineLength();
        break;
      case Action::SELECT_LEVEL_DOWN:
        $lines = count($this->lines) - 1;
        $this->newcaret[0] = $lines;
        $this->newcaret[1] = mb_strlen($this->lines[$lines]);
        break;
      /* LEFT */
      case Action::MOVE_LEFT:
        $this->moveBackward();
        $this->resetSelection();
        break;
      case Action::MOVE_FIRST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->newcaret[1] = max(0, $this->newcaret[1] - $lettersOnScreen);
        $this->resetSelection();
        break;
      case Action::MOVE_START:
        $this->newcaret[1] = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_LEFT:
        $this->moveBackward();
        break;
      case Action::SELECT_FIRST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->newcaret[1] = max(0, $this->newcaret[1] - $lettersOnScreen);
        break;
      case Action::SELECT_START:
        $this->newcaret[1] = 0;
        break;
      /* RIGHT */
      case Action::MOVE_RIGHT:
        $this->moveForward();
        $this->resetSelection();
        break;
      case Action::MOVE_LAST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->newcaret[1] += $lettersOnScreen;
        $this->checkLineLength();
        $this->resetSelection();
        break;
      case Action::MOVE_END:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth) - 1;
        $this->newcaret[1] = mb_strlen($this->lines[$this->newcaret[0]]);
        $this->resetSelection();
        break;
      case Action::SELECT_RIGHT:
        $this->moveForward();
        break;;
      case Action::SELECT_LAST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->newcaret[1] += $lettersOnScreen;
        $this->checkLineLength();
        break;
      case Action::SELECT_END:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth) - 1;
        $this->newcaret[1] = mb_strlen($this->lines[$this->newcaret[0]]);
        break;
      /* DELETE */
      case Action::DELETE_BACK:
        $this->selectionToCoordinates($row1, $col1, $row2, $col2);
        $line = $this->lines[$row1];
        if ($row1 === $row2 && $col1 === 0 && $col2 === 1) {
          if ($row1 > 0) {
            $row1--;
            $line2 = $this->lines[$row1];
            $len = mb_strlen($line2);
            $this->lineSplice($row1, 2, [$line2 . $line]);
            $this->newcaret[0]--;
            $this->newcaret[1] = $len;
          }
        } else if ($row1 === $row2 && $col1 === $col2 - 1) {
          $this->newcaret[1]--;
          $this->newanchor[1]--;
          $this->replaceSelection([''], false);
        } else {
          $this->replaceSelection([''], false);
          $this->newcaret[0] = $row1;
          $this->newcaret[1] = $col1;
        }
        $this->resetSelection();
        break;
      case Action::DELETE_FORWARD:
        $this->selectionToCoordinates($row1, $col1, $row2, $col2);
        $line = $this->lines[$row1];
        $len = mb_strlen($line);
        if ($row1 === $row2 && $col1 === $len && $col2 === $len + 1) {
          $lcnt = count($this->lines);
          if ($row1 < $lcnt) {
            $line2 = $this->lines[$row1 + 1];
            $this->lineSplice($row1, 2, [$line . $line2]);
          }
        } else {
          $this->replaceSelection([''], false);
          $this->newcaret[0] = $row1;
          $this->newcaret[1] = $col1;
        }
        $this->resetSelection();
        break;
      /* COPY-PASTE */
      case Action::CUT:
        $this->selectionToCoordinates($row1, $col1, $row2, $col2);
        Clipboard::set($this->getSelection());
        $this->replaceSelection([''], false);
        $this->newcaret[0] = $row1;
        $this->newcaret[1] = $col1;
        $this->resetSelection();
        break;
      case Action::COPY:
        Clipboard::set($this->getSelection());
        $this->resetSelection();
        break;
      case Action::PASTE:
        $paste = Clipboard::get();
        if ($paste !== false) {
          $lines = explode("\n", $paste);
          $this->replaceSelection($lines);
        }
        break;
      /* UNDO-REDO */
      case Action::UNDO:
        if ($this->lineUnderConstruction !== false) {
          $this->newcaret = $this->originalCursor[0];
          $this->newanchor = $this->originalCursor[1];
          $this->redo[] = [$this->lineUnderConstruction, [$this->originalLine], [$this->lines[$this->lineUnderConstruction]], $this->originalCursor, [$this->caret, $this->anchor]];
          $this->lineSplice($this->lineUnderConstruction, 1, [$this->originalLine], false);
          $this->originalLine = false;
          $this->originalCursor = false;
          $this->lineUnderConstruction = false;
        } else if (!empty($this->undo)) {
          $splice = array_pop($this->undo);
          $this->redo[] = $splice;
          $this->lineSplice($splice[0], count($splice[2]), $splice[1], false);
          $this->newcaret = $splice[3][0];
          $this->newanchor = $splice[3][1];
        }
        break;
      case Action::REDO:
        if (!empty($this->redo)) {
          $splice = array_pop($this->redo);
          $this->undo[] = $splice;
          $this->lineSplice($splice[0], count($splice[1]), $splice[2], false);
          $this->newcaret = $splice[4][0];
          $this->newanchor = $splice[4][1];
          $this->resetSelection();
        }
        break;
      default:
        return false;
    }
    $this->saveCursor();
    $this->update();
    return true;
  }

  public function textInputHandler($element, $event) {
    $this->initNewCursor();
    $this->selectionToCoordinates($row1, $col1, $row2, $col2);
    $this->replaceSelection([$event['text']]);
    $this->newcaret[0] = $row1;
    $this->newcaret[1] = $col1;
    $this->newcaret[1]++;
    $this->resetSelection();
    $this->saveCursor();
    $this->update();
    return true;
  }

}
