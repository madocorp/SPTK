<?php

namespace SPTK;

class TextEditor extends Element {

  protected $lines = [];
  protected $row = [0, 0];
  protected $col = [0, 1];
  protected $selectDirection = [0, 0];
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
    $selected = ($this->row[0] < $firstOnScreen && $this->row[1] >= $firstOnScreen);
    foreach ($tokens as $i => $line) {
      $row = new InputRow($this);
      $row->setPos($i, $this->lineHeight);
      $j = 0;
      foreach ($line as $token) {
        if ($i === $this->row[0] && $this->col[0] > $j && $this->col[0] < $j + $token['length']) {
          $split = $this->col[0] - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $this->row[0] && $j === $this->col[0]) {
          $selected = true;
        }
        if ($i === $this->row[1] && $this->col[1] > $j && $this->col[1] < $j + $token['length']) {
          $split = $this->col[1] - $j;
          $token = $this->splitToken($token, $split, $selected, $row);
          $j += $split;
        }
        if ($i === $this->row[1] && $j === $this->col[1]) {
          $selected = false;
        }
        if ($selected) {
          $token['style'] .= ' InputValue:selected';
        }
        $iv = new InputValue($row, false, $token['style']);
        $iv->setValue($token['value']);
        $j += $token['length'];
      }
      if ($this->row[0] === $i && $this->col[0] === $j) {
        $selected = true;
      }
      if ($this->row[1] === $i && $this->col[1] === $j) {
        $selected = false;
      }
      $style = false;
      if ($selected) {
        $style = 'InputValue:selected';
        if ($this->row[0] != $this->row[1] || $this->row[0] < $this->row[1] - 1) {
          $style = 'InputValue:newline';
        }
      }
      $iv = new InputValue($row, false, $style);
      $iv->setValue(' ');
      $j++;
      if ($this->row[1] === $i && $this->col[1] === $j) {
        $selected = false;
      }

    }
  }

  protected function setScroll() {
    if ($this->selectDirection[0] > 0 || $this->selectDirection[1] > 0) {
      $row = $this->row[1];
      $col = $this->col[1];
    } else {
      $row = $this->row[0];
      $col = $this->col[0];
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

  protected function checkDocStart($c) {
    if ($this->row[$c] < 0) {
      $this->row[$c] = 0;
    }
  }

  protected function checkDocEnd($c) {
    $lcnt = count($this->lines);
    if ($this->row[$c] >= $lcnt) {
      $this->row[$c] = $lcnt - 1;
    }
  }

  protected function checkLineLength($c) {
    $len = mb_strlen($this->lines[$this->row[$c]]);
    if ($this->col[$c] > $len + $c) {
      $this->col[$c] = $len + $c;
    }
  }

  protected function moveForward($c) {
    $len = mb_strlen($this->lines[$this->row[$c]]);
    if ($this->col[$c] < $len + $c) {
      $this->col[$c]++;
    } else {
      $lcnt = count($this->lines);
      if ($this->row[$c] < $lcnt - 1) {
        $this->row[$c]++;
        $this->col[$c] = $c;
      }
    }
  }

  protected function moveBackward($c) {
    if ($this->col[$c] > $c) {
      $this->col[$c]--;
    } else {
      if ($this->row[$c] > 0) {
        $this->row[$c]--;
        $this->col[$c] = mb_strlen($this->lines[$this->row[$c]]) + $c;
      }
    }
  }

  protected function resetSelection() {
    $this->row[1] = $this->row[0];
    $this->col[1] = $this->col[0] + 1;
    $this->selectDirection[0] = 0;
    $this->selectDirection[1] = 0;
  }

  protected function checkSelection() {
    if ($this->row[0] === $this->row[1] && $this->col[0] === $this->col[1]) {
      $this->col[1]++;
    }
    if ($this->row[0] === $this->row[1]) {
      $this->selectDirection[0] = 0;
      if ($this->col[1] < $this->col[0]) {
        $tmp = $this->col[1];
        $this->col[1] = $this->col[0];
        $this->col[0] = $tmp;
      }
      if ($this->col[0] === $this->col[1] - 1) {
        $this->selectDirection[1] = 0;
      }
    }
  }

  protected function replaceSelection($newLines) {
    $before = mb_substr($this->lines[$this->row[0]], 0, $this->col[0]);
    $after = mb_substr($this->lines[$this->row[1]], $this->col[1]);
    $last = count($newLines) - 1;
    $newLines[0] = $before . $newLines[0];
    $newLines[$last] = $newLines[$last] . $after;
    $this->lineSplice($this->row[0], $this->row[1] - $this->row[0] + 1, $newLines);
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      /* SPACE */
      case Action::SELECT_ITEM:
        return true;
      /* UP */
      case Action::MOVE_UP:
        $this->row[0]--;
        $this->checkDocStart(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::PAGE_UP:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row[0] -= $linesOnScreen;
        $this->checkDocStart(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::LEVEL_UP:
        $this->row[0] = 0;
        $this->col[0] = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_UP:
        if ($this->selectDirection[0] <= 0) {
          $this->row[0]--;
          $this->checkDocStart(0, 1);
          $this->checkLineLength(0);
          $this->selectDirection[0] = -1;
          $this->selectDirection[1] = -1;
        } else {
          $this->row[1]--;
          $this->checkLineLength(1, 1);
        }
        $this->checkSelection();
        break;
      case Action::SELECT_PAGE_UP:
// TODO
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row[0] -= $linesOnScreen;
        $this->checkDocStart(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::SELECT_LEVEL_UP:
        if ($this->selectDirection[0] > 0 || $this->selectDirection[1] > 0) {
          $tmp = $this->col[1]; $this->col[1] = $this->col[0]; $this->col[0] = $tmp;
          $tmp = $this->row[1]; $this->row[1] = $this->row[0]; $this->row[0] = $tmp;
        }
        $this->row[0] = 0;
        $this->col[0] = 0;
        $this->selectDirection[0] = -1;
        $this->selectDirection[1] = -1;
        break;
      /* DOWN */
      case Action::MOVE_DOWN:
        $this->row[0]++;
        $this->checkDocEnd(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::PAGE_DOWN:
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row[0] += $linesOnScreen;
        $this->checkDocEnd(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::LEVEL_DOWN:
        $lines = count($this->lines) - 1;
        $this->row[0] = $lines;
        $this->col[0] = mb_strlen($this->lines[$lines]);
        $this->resetSelection();
        break;
      case Action::SELECT_DOWN:
        if ($this->selectDirection[0] >= 0) {
          $this->row[1]++;
          $this->checkDocEnd(1);
          $this->checkLineLength(1, 1);
          $this->selectDirection[0] = 1;
          $this->selectDirection[1] = 1;
        } else {
          $this->row[0]++;
          $this->checkLineLength(0, 1);
        }
        $this->checkSelection();
        break;
      case Action::SELECT_PAGE_DOWN:
// TODO
        $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
        $this->row[0] += $linesOnScreen;
        $this->checkDocEnd(0);
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::SELECT_LEVEL_DOWN:
        if ($this->selectDirection[0] < 0 || $this->selectDirection[1] < 0) {
          $tmp = $this->col[1]; $this->col[1] = $this->col[0]; $this->col[0] = $tmp;
          $tmp = $this->row[1]; $this->row[1] = $this->row[0]; $this->row[0] = $tmp;
        }
        $lines = count($this->lines) - 1;
        $this->row[1] = $lines;
        $this->col[1] = mb_strlen($this->lines[$lines]) + 1;
        $this->selectDirection[0] = 1;
        $this->selectDirection[1] = 1;
        break;
      /* LEFT */
      case Action::MOVE_LEFT:
        $this->moveBackward(0);
        $this->resetSelection();
        break;
      case Action::MOVE_FIRST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->col[0] = max(0, $this->col[0] - $lettersOnScreen);
        $this->resetSelection();
        break;
      case Action::MOVE_START:
        $this->col[0] = 0;
        $this->resetSelection();
        break;
      case Action::SELECT_LEFT:
        if ($this->selectDirection[1] <= 0) {
          $this->moveBackward(0);
          $this->selectDirection[1] = -1;
        } else {
          $this->moveBackward(1);
        }
        $this->checkSelection();
        break;
      case Action::SELECT_FIRST:
        $lettersOnScreen = (int)($this->geometry->width / $this->letterWidth) - 1;
        if ($this->selectDirection[1] <= 0) {
          $this->col[0] = max(0, $this->col[0] - $lettersOnScreen + 1);
          $this->selectDirection[1] = -1;
        } else {
          $this->col[1] = max(1, $this->col[1] - $lettersOnScreen);
        }
        $this->checkSelection();
        break;
      case Action::SELECT_START:
        if ($this->selectDirection[1] <= 0) {
          $this->col[0] = 0;
          $this->selectDirection[1] = -1;
        } else {
          $this->col[1] = 1;
        }
        $this->checkSelection();
        break;
      /* RIGHT */
      case Action::MOVE_RIGHT:
        $this->moveForward(0);
        $this->resetSelection();
        break;
      case Action::MOVE_LAST:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
        $this->col[0] += $lettersOnScreen;
        $this->checkLineLength(0);
        $this->resetSelection();
        break;
      case Action::MOVE_END:
        $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth) - 1;
        $this->col[0] = mb_strlen($this->lines[$this->row[0]]);
        $this->resetSelection();
        break;
      case Action::SELECT_RIGHT:
        if ($this->selectDirection[1] >= 0) {
          $this->moveForward(1);
          $this->selectDirection[1] = 1;
        } else {
          $this->moveForward(0);
        }
        $this->checkSelection();
        break;;
      case Action::SELECT_LAST:
        $lettersOnScreen = (int)($this->geometry->width / $this->letterWidth) - 1;
        if ($this->selectDirection[1] >= 0) {
          $this->col[1] += $lettersOnScreen - 1;
          $this->checkLineLength(1);
          $this->selectDirection[1] = 1;
        } else {
          $this->col[0] += $lettersOnScreen;
          $this->checkLineLength(0);
        }
        $this->checkSelection();
        break;
      case Action::SELECT_END:
        if ($this->selectDirection[1] >= 0) {
          $this->col[1] = mb_strlen($this->lines[$this->row[1]]) + 1;
          $this->selectDirection[1] = 1;
        } else {
          $this->col[0] = mb_strlen($this->lines[$this->row[0]]);
        }
        $this->checkSelection();
        break;
      /* DELETE */
      case Action::DELETE_BACK:
        $line = $this->lines[$this->row[0]];
        if ($this->row[0] === $this->row[1] && $this->col[0] === 0 && $this->col[1] === 1) {
          if ($this->row[0] > 0) {
            $this->row[0]--;
            $line2 = $this->lines[$this->row[0]];
            $len = mb_strlen($line2);
            $this->col[0] = $len;
            $this->lineSplice($this->row[0], 2, $line2 . $line);
            $this->resetSelection();
          }
        } else if ($this->row[0] === $this->row[1] && $this->col[0] === $this->col[1] - 1) {
          $this->col[0]--;
          $this->col[1]--;
          $this->replaceSelection(['']);
        } else {
          $this->replaceSelection(['']);
          $this->resetSelection();
        }
        break;
      case Action::DELETE_FORWARD:
        $line = $this->lines[$this->row[0]];
        $len = mb_strlen($line);
        if ($this->row[0] === $this->row[1] && $this->col[0] === $len && $this->col[1] === $len + 1) {
          $lcnt = count($this->lines);
          if ($this->row[0] < $lcnt) {
            $line2 = $this->lines[$this->row[0] + 1];
            $this->lineSplice($this->row[0], 2, $line . $line2);
          }
        } else {
          $this->replaceSelection(['']);
          $this->resetSelection();
        }
        break;
      /* COPY-PASTE*/
      case Action::CUT:
        return true;
      case Action::COPY:
        return true;
      case Action::PASTE:
        return true;
      default:
        return false;
    }
echo "{$this->row[0]}:{$this->col[0]}   {$this->row[1]}:{$this->col[1]}\n";
    $this->update();
    return true;
  }

  public function textInputHandler($element, $event) {
    if ($this->row[0] != $this->row[1] || $this->col[1] - $this->col[0] > 1) {
    } else {
      $cline = $this->lines[$this->row[0]];
      $this->lines[$this->row[0]] = mb_substr($cline, 0, $this->col[0]) . $event['text'] . mb_substr($cline, $this->col[0]);
      $this->col[0]++;
      $this->col[1]++;
    }
    $this->selectDirection[0] = 0;
    $this->selectDirection[1] = 0;
    $this->update();
    return true;
  }

}
