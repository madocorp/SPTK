<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;
use \SPTK\Clipboard;

class TextBox extends Element {

  protected $lines = [''];
  protected $cursor;
  protected $lineHeight;
  protected $letterWidth;
  protected $tokenizer = [];
  protected $lineTokens = [];
  protected $lineContexts = [];
  protected $active = false;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $fontSize = $this->style->get('fontSize');
    $fontName = $this->style->get('font');
    $font = new Font($fontName, $fontSize);
    $this->letterWidth = $font->letterWidth;
    $this->lineHeight = $font->height;
    $this->cursor = new \SPTK\Elements\TextEditor\Cursor($this->lines);
  }

  public function getAttributeList(): array {
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

  public function setValue($value): void {
    $this->lines = explode("\n", $value);
    $this->lineTokens = [];
    $this->lineContexts = [];
    $this->cursor->modify(0, 0, 0, 0);
    $this->cursor->save();
    $this->scrollX = 0;
    $this->scrollY = 0;
    if ($this->renderer === false) {
      return;
    }
    $this->measure();
    $this->update();
  }

  public function getValue(): mixed {
    return $this->lines;
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      $this->active = true;
    }
    parent::addVariant($class);
    $this->update();
  }

  public function removeVariant(string $class): void {
    if ($class == 'active') {
      $this->active = false;
    }
    parent::removeVariant($class);
    $this->update();
  }

  protected function calculateWidths(): void {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateWidths();
    }
  }

  protected function calculateHeights(): void {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $maxY = count($this->lines) * $this->lineHeight + $this->geometry->borderTop + $this->geometry->paddingTop;
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent, $maxY);
  }

  protected function layout(): void {
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

  protected function invalidateTokensFrom(int $line): void {
    foreach (array_keys($this->lineTokens) as $i) {
      if ($i >= $line) {
        unset($this->lineTokens[$i]);
      }
    }
    foreach (array_keys($this->lineContexts) as $i) {
      if ($i >= $line) {
        unset($this->lineContexts[$i]);
      }
    }
  }

  protected function tokenize($from, $to) {
    $result = [];
    $missingFrom = false;
    for ($i = $from; $i < $to; $i++) {
      if (isset($this->lineTokens[$i])) {
        $result[$i] = $this->lineTokens[$i];
        continue;
      }
      $missingFrom = $i;
      break;
    }
    if ($missingFrom === false) {
      return $result;
    }
    $context = $this->tokenizer;
    $tokenizeFrom = $missingFrom;
    while ($tokenizeFrom > 0 && !isset($this->lineContexts[$tokenizeFrom - 1])) {
      $tokenizeFrom--;
    }
    if ($tokenizeFrom > 0) {
      $context = $this->lineContexts[$tokenizeFrom - 1];
    }
    $lines = array_slice($this->lines, $tokenizeFrom, $to - $tokenizeFrom);
    $tokens = \SPTK\Tokenizer::start($lines, $context);
    for ($i = $tokenizeFrom; $i < $to; $i++) {
      $lineTokens = array_shift($tokens);
      $this->lineContexts[$i] = $lineTokens['context'];
      $this->lineTokens[$i] = $lineTokens['tokens'];
      if ($i >= $from) {
        $result[$i] = $lineTokens['tokens'];
      }
    }
    return $result;
  }

  protected function cursorCoordinates(array $cursor): array {
    if (
      $cursor[0] < $cursor[2] ||
      ($cursor[0] == $cursor[2] && $cursor[1] <= $cursor[3])
    ) {
      return [$cursor[0], $cursor[1], $cursor[2], $cursor[3] + 1];
    }
    return [$cursor[2], $cursor[3], $cursor[0], $cursor[1] + 1];
  }

  protected function affectedCursorRows(array $before, array $after): array {
    [$oldRow1, , $oldRow2, ] = $this->cursorCoordinates($before);
    [$newRow1, , $newRow2, ] = $this->cursorCoordinates($after);
    return range(min($oldRow1, $newRow1), max($oldRow2, $newRow2));
  }

  protected function screenRange(): array {
    if ($this->geometry->height == 0) {
      return [0, min(300, count($this->lines))];
    }
    if ($this->geometry->height === 'content') {
      $this->height = count($this->lines) * $this->lineHeight + $this->geometry->paddingTop + $this->geometry->paddingBottom + $this->geometry->borderTop + $this->geometry->borderBottom;
      return [0, min(300, count($this->lines))];
    }
    $firstOnScreen = max(0, (int)(($this->scrollY + $this->geometry->paddingTop) / $this->lineHeight) - 1);
    $lastOnScreen = min($firstOnScreen + (int)($this->geometry->height / $this->lineHeight) + 1, count($this->lines));
    return [$firstOnScreen, $lastOnScreen];
  }

  protected function lineSelectionRange(int $lineNumber, array $cursor): array|false {
    [$row1, $col1, $row2, $col2] = $this->cursorCoordinates($cursor);
    if ($lineNumber < $row1 || $lineNumber > $row2) {
      return false;
    }
    $from = $lineNumber === $row1 ? $col1 : 0;
    $to = $lineNumber === $row2 ? $col2 : mb_strlen($this->lines[$lineNumber]) + 1;
    return [$from, $to, $row1, $row2];
  }

  protected function buildLineContent(InputRow $row, int $lineNumber, array $tokens, array $cursor): void {
    $selection = $this->lineSelectionRange($lineNumber, $cursor);
    $j = 0;
    foreach ($tokens as $token) {
      $style = $token['style'];
      $tokenStart = $j;
      $tokenEnd = $j + $token['length'];
      if ($selection !== false && $this->active) {
        [$selectedFrom, $selectedTo] = $selection;
        $selectStart = max($tokenStart, $selectedFrom);
        $selectEnd = min($tokenEnd, $selectedTo);
        if ($selectStart < $selectEnd) {
          if ($selectStart > $tokenStart) {
            $iv = new InputValue($row, null, $style);
            $iv->setValue(mb_substr($token['value'], 0, $selectStart - $tokenStart));
          }
          $iv = new InputValue($row, null, $style . ' InputValue:selected');
          $iv->setValue(mb_substr($token['value'], $selectStart - $tokenStart, $selectEnd - $selectStart));
          if ($selectEnd < $tokenEnd) {
            $iv = new InputValue($row, null, $style);
            $iv->setValue(mb_substr($token['value'], $selectEnd - $tokenStart));
          }
          $j = $tokenEnd;
          continue;
        }
      }
      $iv = new InputValue($row, null, $style);
      $iv->setValue($token['value']);
      $j = $tokenEnd;
    }
    $style = false;
    if ($selection !== false && $this->active) {
      [$selectedFrom, $selectedTo, $row1, $row2] = $selection;
      if ($j >= $selectedFrom && $j < $selectedTo) {
        $style = 'InputValue:selected';
        if ($row1 != $row2 || $lineNumber < $row2 - 1) {
          $style = 'InputValue:newline';
        }
      }
    }
    $iv = new InputValue($row, null, $style);
    $iv->setValue(' ');
  }

  protected function buildLine(int $lineNumber, array $tokens, array $cursor): InputRow {
    $row = new InputRow($this);
    $row->setValue($lineNumber);
    $row->setPos($lineNumber, $this->lineHeight);
    $this->buildLineContent($row, $lineNumber, $tokens, $cursor);
    return $row;
  }

  protected function splitToken($token, $split, $selected, $row) {
    $style = $token['style'];
    if ($selected && $this->active) {
      $style .= ' InputValue:selected';
    }
    $iv = new InputValue($row, null, $style);
    $iv->setValue(mb_substr($token['value'], 0, $split));
    $token['value'] = mb_substr($token['value'], $split);
    $token['length'] -= $split;
    return $token;
  }

  protected function buildTree($firstOnScreen, $tokens) {
    $this->clear();
    $cursor = $this->cursor->get();
    foreach ($tokens as $i => $line) {
      $this->buildLine($i, $line, $cursor);
    }
  }

  protected function rebuildVisibleLine(int $lineNumber, array $cursor): InputRow|false {
    foreach ($this->descendants as $row) {
      if ($row->getType() === 'InputRow' && $row->getValue() === $lineNumber) {
        $tokens = $this->tokenize($lineNumber, $lineNumber + 1);
        $row->clear();
        $row->setPos($lineNumber, $this->lineHeight);
        $this->buildLineContent($row, $lineNumber, $tokens[$lineNumber] ?? [], $cursor);
        $row->recalculateGeometry();
        return $row;
      }
    }
    return false;
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

  private function isVisibleInTree(): bool {
    $element = $this;
    while ($element !== null) {
      if (!$element->isDisplayed()) {
        return false;
      }
      $element = $element->getAncestor();
    }
    return true;
  }

  protected function update() {
    $this->cursor->save();
    $this->setScroll();
    [$firstOnScreen, $lastOnScreen] = $this->screenRange();
    $tokens = $this->tokenize($firstOnScreen, $lastOnScreen);
    $this->buildTree($firstOnScreen, $tokens);
    if (!$this->isVisibleInTree()) {
      return;
    }
    Element::immediateRender($this);
  }

  protected function updateCursor(): void {
    $before = $this->cursor->getBefore();
    $after = $this->cursor->get();
    $scrollX = $this->scrollX;
    $scrollY = $this->scrollY;
    $this->setScroll();
    if ($scrollX !== $this->scrollX || $scrollY !== $this->scrollY) {
      $this->update();
      return;
    }
    [$firstOnScreen, $lastOnScreen] = $this->screenRange();
    $rows = [];
    foreach ($this->affectedCursorRows($before, $after) as $lineNumber) {
      if ($lineNumber >= $firstOnScreen && $lineNumber < $lastOnScreen) {
        $row = $this->rebuildVisibleLine($lineNumber, $after);
        if ($row === false) {
          $this->update();
          return;
        }
        $rows[] = $row;
      }
    }
    $this->cursor->save();
    if (!$this->isVisibleInTree()) {
      return;
    }
    foreach ($rows as $row) {
      Element::immediateRefresh($row, false);
    }
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
