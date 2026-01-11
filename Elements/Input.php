<?php

namespace SPTK;

class Input extends Element {

  protected $elementBefore;
  protected $elementSelected;
  protected $elementAfter;
  protected $placeholder = '';
  protected $onChange = false;
  protected $lines = [];
  protected $cursor;
  protected $history;

  protected function init() {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->elementBefore = new InputValue($this);
    $this->elementSelected = new InputValue($this);
    $this->elementAfter = new InputValue($this);
    $this->setValue('');
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $fontSize);
    $this->letterWidth = $font->letterWidth;
    $this->cursor = new \SPTK\TextEditor\Cursor($this->lines);
    $this->history = new \SPTK\TextEditor\History($this->lines, $this->cursor);
  }

  public function getAttributeList() {
    return ['value', 'placeholder', 'onChange'];
  }

  public function setValue($value) {
    if ($value === false) {
      return;
    }
    $this->lines = [$value];
  }

  public function getValue() {
    return $this->lines[0];
  }

  public function setPlaceholder($value) {
    $this->placeholder = $value;
  }

  public function setOnChange($value) {
    if ($value === false) {
      return;
    }
    if (is_array($value)) {
      $this->onChange = $value;
    } else {
      $this->onChange = self::parseCallback($value);
    }
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->addClass('selected', true);
      if ($this->getValue() === '') {
        $this->elementBefore->setValue($this->placeholder);
        $this->elementBefore->addClass('placeholder', true);
      }
    }
    parent::addClass($class, $dynamic);
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic && $class == 'active') {
      $this->elementSelected->removeClass('selected', true);
    }
    if ($this->getValue() === '') {
      $this->elementBefore->setValue('');
      $this->elementBefore->removeClass('placeholder', true);
    }
    parent::removeClass($class, $dynamic);
  }

  protected function setScroll() {
    $selected = $this->elementSelected;
    if ($selected->geometry->x + $selected->geometry->width > $this->scrollX + $this->geometry->width - $this->geometry->borderLeft) {
      $this->scrollX = $selected->geometry->x + $selected->geometry->width - $this->geometry->width + $this->geometry->borderLeft;
    } else if ($selected->geometry->x < $this->scrollX) {
      $this->scrollX = $selected->geometry->x;
    }
  }

  protected function update() {
    $this->cursor->save();
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[0], 0, $col1);
    $selected = mb_substr($this->lines[0], $col1, $col2 - $col1);
    $after = mb_substr($this->lines[0], $col2);
    $this->elementBefore->setValue($before);
    $this->elementSelected->setValue($selected === '' ? ' ' : $selected);
    $this->elementAfter->setValue($after);
    $this->recalculateGeometry();
    $this->setScroll();
    Element::immediateRender($this);
  }

  protected function insert($insert) {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[0], 0, $col1);
    $after = mb_substr($this->lines[0], $col1);
    $this->change("{$before}{$insert}{$after}");
  }

  protected function replace($replacement) {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[0], 0, $col1);
    $after = mb_substr($this->lines[0], $col2);
    $this->change("{$before}{$replacement}{$after}");
  }

  protected function change($newValue) {
    $this->history->store(0, 1, [$newValue]);
    $this->lines[0] = $newValue;
    if ($this->onChange !== false) {
      call_user_func($this->onChange, $this);
    }
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    switch ($keycombo) {
      /* SPACE */
      case Action::SELECT_ITEM:
        return true;
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
        if ($col1 === $col2 - 1) {
          $this->cursor->modify($row1, $col1 - 1, $row1, $col1 - 1);
          $this->cursor->save();
          $this->replace('');
        } else {
          $this->cursor->modify($row1, $col1, $row1, $col1);
          $this->replace('');
        }
        break;
      case Action::DELETE_FORWARD:
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $this->cursor->modify($row1, $col1, $row1, $col1);
        $this->replace('');
        break;
      /* COPY-PASTE */
      case Action::CUT:
        Clipboard::set($this->cursor->getSelection());
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $this->cursor->modify($row1, $col1, $row1, $col1);
        $this->replace('');
        break;
      case Action::COPY:
        Clipboard::set($this->cursor->getSelection());
        $this->cursor->resetSelection();
        break;
      case Action::PASTE:
        $paste = Clipboard::get();
        if ($paste !== false) {
          $lines = explode("\n", $paste, 2);
          $paste = $lines[0];
          $len = mb_strlen($paste);
          $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
          $this->cursor->modify($row1, $col1 + $len , $row1, $col1 + $len);
          if ($col1 === $col2 - 1) {
            $this->insert($paste);
          } else {
            $this->replace($paste);
          }
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
    if ($this->lines[0] === '') {
      $this->elementBefore->removeClass('placeholder', true);
    }
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    if ($col1 === $col2 - 1) {
      $this->cursor->modify($row1, $col1 + 1, $row1, $col1 + 1);
      $this->insert($event['text']);
    } else {
      $this->cursor->modify($row1, $col1 + 1, $row1, $col1 + 1);
      $this->replace($event['text']);
    }
    $this->update();
    return true;
  }

}
