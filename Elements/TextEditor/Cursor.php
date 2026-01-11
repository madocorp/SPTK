<?php

namespace SPTK\TextEditor;

use \SPTK\Action;

class Cursor {

  private $lines;
  private $caret = [0, 0];
  private $anchor = [0, 0];
  private $caretBefore = [0, 0];
  private $anchorBefore = [0, 0];

  public function __construct(&$lines) {
    $this->lines = &$lines;
  }

  protected function checkDocStart() {
    $this->caret[0] = max(0, $this->caret[0]);
  }

  protected function checkDocEnd() {
    $lcnt = count($this->lines);
    $this->caret[0] = min($lcnt - 1, $this->caret[0]);
  }

  protected function checkLineLength() {
    $len = mb_strlen($this->lines[$this->caret[0]]);
    $this->caret[1] = min($len, $this->caret[1]);
  }

  public function save() {
    $this->caretBefore = $this->caret;
    $this->anchorBefore = $this->anchor;
  }

  public function get() {
    return [$this->caret[0], $this->caret[1], $this->anchor[0], $this->anchor[1]];
  }

  public function getBefore() {
    return [$this->caretBefore[0], $this->caretBefore[1], $this->anchorBefore[0], $this->anchorBefore[1]];
  }

  public function toCoordinates(&$row1, &$col1, &$row2, &$col2) {
    $caret = $this->caretBefore;
    $anchor = $this->anchorBefore;
    if (
      $caret[0] < $anchor[0] ||
      ($caret[0] == $anchor[0] && $caret[1] <= $anchor[1])
    ) {
      $row1 = $caret[0];
      $col1 = $caret[1];
      $row2 = $anchor[0];
      $col2 = $anchor[1] + 1;
    } else {
      $row1 = $anchor[0];
      $col1 = $anchor[1];
      $row2 = $caret[0];
      $col2 = $caret[1] + 1;
    }
  }

  public function set($cursor) {
    $this->caret[0] = $cursor[0];
    $this->caret[1] = $cursor[1];
    $this->anchor[0] = $cursor[2];
    $this->anchor[1] = $cursor[3];
  }

  public function modify($caretRow, $caretCol, $anchorRow, $anchorCol) {
    if ($caretRow !== false) {
      $this->caret[0] = $caretRow;
    }
    if ($caretCol !== false) {
      $this->caret[1] = $caretCol;
    }
    if ($anchorRow !== false) {
      $this->anchor[0] = $anchorRow;
    }
    if ($anchorCol !== false) {
      $this->anchor[1] = $anchorCol;
    }
  }

  public function getSelection() {
    $this->toCoordinates($row1, $col1, $row2, $col2);
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

  public function resetSelection($select = false) {
    if ($select) {
      return;
    }
    $this->anchor[0] = $this->caret[0];
    $this->anchor[1] = $this->caret[1];
  }

  public function moveUp($select = false) {
    $this->caret[0]--;
    $this->checkDocStart();
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function movePageUp($linesOnScreen, $select = false) {
    $this->caret[0] -= $linesOnScreen;
    $this->checkDocStart();
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function moveDocStart($select = false) {
    $this->caret[0] = 0;
    $this->caret[1] = 0;
    $this->resetSelection($select);
  }

  public function moveDown($select = false) {
    $this->caret[0]++;
    $this->checkDocEnd();
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function movePageDown($linesOnScreen, $select = false) {
    $this->caret[0] += $linesOnScreen;
    $this->checkDocEnd();
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function moveDocEnd($select = false) {
    $lines = count($this->lines) - 1;
    $this->caret[0] = $lines;
    $this->caret[1] = mb_strlen($this->lines[$lines]);
    $this->resetSelection($select);
  }

  public function moveForward($select = false) {
    $len = mb_strlen($this->lines[$this->caret[0]]);
    if ($this->caret[1] < $len) {
      $this->caret[1]++;
    } else {
      $lcnt = count($this->lines);
      if ($this->caret[0] < $lcnt - 1) {
        $this->caret[0]++;
        $this->caret[1] = 0;
      }
    }
    $this->resetSelection($select);
  }

  public function moveScreenEnd($lettersOnScreen, $select = false) {
    $this->caret[1] += $lettersOnScreen;
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function moveLineEnd($select = false) {
    $this->caret[1] = mb_strlen($this->lines[$this->caret[0]]);
    $this->resetSelection($select);
  }

  public function moveBackward($select = false) {
    if ($this->caret[1] > 0) {
      $this->caret[1]--;
    } else {
      if ($this->caret[0] > 0) {
        $this->caret[0]--;
        $this->caret[1] = mb_strlen($this->lines[$this->caret[0]]);
      }
    }
    $this->resetSelection($select);
  }

  public function moveScreenStart($lettersOnScreen, $select = false) {
    $this->caret[1] = max(0, $this->caret[1] - $lettersOnScreen);
    $this->resetSelection($select);
  }

  public function moveLineStart($select = false) {
    $this->caret[1] = 0;
    $this->resetSelection($select);
  }

  public function handleKeys($keycombo, $linesOnScreen, $lettersOnScreen) {
    switch ($keycombo) {
      /* UP */
      case Action::MOVE_UP:
        $this->moveUp();
        break;
      case Action::PAGE_UP:
        $this->movePageUp($linesOnScreen);
        break;
      case Action::LEVEL_UP:
        $this->moveDocStart();
        break;
      case Action::SELECT_UP:
        $this->moveUp(true);
        break;
      case Action::SELECT_PAGE_UP:
        $this->movePageUp($linesOnScreen, true);
        break;
      case Action::SELECT_LEVEL_UP:
        $this->moveDocStart(true);
        break;
      /* DOWN */
      case Action::MOVE_DOWN:
        $this->moveDown();
        break;
      case Action::PAGE_DOWN:
        $this->movePageDown($linesOnScreen);
        break;
      case Action::LEVEL_DOWN:
        $this->moveDocEnd();
        break;
      case Action::SELECT_DOWN:
        $this->moveDown(true);
        break;
      case Action::SELECT_PAGE_DOWN:
        $this->movePageDown($linesOnScreen, true);
        break;
      case Action::SELECT_LEVEL_DOWN:
        $this->moveDocEnd(true);
        break;
      /* LEFT */
      case Action::MOVE_LEFT:
        $this->moveBackward();
        break;
      case Action::MOVE_FIRST:
        $this->moveScreenStart($lettersOnScreen);
        break;
      case Action::MOVE_START:
        $this->moveLineStart();
        break;
      case Action::SELECT_LEFT:
        $this->moveBackward(true);
        break;
      case Action::SELECT_FIRST:
        $this->moveScreenStart($lettersOnScreen, true);
        break;
      case Action::SELECT_START:
        $this->moveLineStart(true);
        break;
      /* RIGHT */
      case Action::MOVE_RIGHT:
        $this->moveForward();
        break;
      case Action::MOVE_LAST:
        $this->moveScreenEnd($lettersOnScreen);
        break;
      case Action::MOVE_END:
        $this->moveLineEnd();
        break;
      case Action::SELECT_RIGHT:
        $this->moveForward(true);
        break;;
      case Action::SELECT_LAST:
        $this->moveScreenEnd($lettersOnScreen, true);
        break;
      case Action::SELECT_END:
        $this->moveLineEnd(true);
        break;
      default:
        return false;
    }
    return true;
  }

}
