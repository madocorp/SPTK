<?php

namespace SPTK;

class TextEditor extends TextBox {

  protected $history;

  protected function init() {
    parent::init();
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->history = new \SPTK\TextEditor\History($this->lines, $this->cursor);
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
