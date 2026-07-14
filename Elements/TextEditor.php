<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\StyleSheet;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;
use \SPTK\Clipboard;

class TextEditor extends TextGrid {

  protected $lines = [''];
  protected $cursor;
  protected $history;
  protected $tokenizer = [];
  protected $lineTokens = [];
  protected $lineContexts = [];
  protected $active = false;
  protected $styleColorCache = [];
  protected $highlightRanges = [];
  protected $preserveScrollOnNextUpdate = false;

  protected function init(): void {
    parent::init();
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->addEvent('TextInput', [$this, 'textInputHandler']);
    $this->cursor = new \SPTK\Elements\TextEditor\Cursor($this->lines);
    $this->history = new \SPTK\Elements\TextEditor\History($this->lines, $this->cursor);
  }

  public function getAttributeList(): array {
    return ['tokenizer', 'file'];
  }

  public function setTokenizer($value): void {
    if ($value === 'false' || $value === false) {
      $value = '\SPTK\Tokenizer';
    }
    $this->tokenizer = $value;
  }

  public function setFile($file): void {
    if ($file === false) {
      return;
    }
    if (strpos($file, '/') !== 0) {
      $file = defined('APP_PATH') ? dirname(APP_PATH) . "/{$file}" : getcwd() . "/{$file}";
    }
    if (file_exists($file)) {
      $content = file_get_contents($file);
      if ($content !== false) {
        $this->setValue($content);
      }
    }
  }

  public function getValue(): mixed {
    return $this->lines;
  }

  public function setValue($value): void {
    $this->lines = explode("\n", $value);
    $this->lineTokens = [];
    $this->lineContexts = [];
    $this->highlightRanges = [];
    $this->cursor = new \SPTK\Elements\TextEditor\Cursor($this->lines);
    $this->history = new \SPTK\Elements\TextEditor\History($this->lines, $this->cursor);
    $this->cursor->modify(0, 0, 0, 0);
    $this->cursor->save();
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->changed = true;
    if ($this->renderer !== false) {
      $this->measure();
      $this->update();
    }
  }

  public function setValueAndState($value, array $state): void {
    $lines = explode("\n", $value);
    $sameValue = $lines === $this->lines;
    $this->lines = $lines;
    $this->lineTokens = [];
    $this->lineContexts = [];
    if (!$sameValue) {
      $this->highlightRanges = [];
    }
    $this->cursor = new \SPTK\Elements\TextEditor\Cursor($this->lines);
    $this->history = new \SPTK\Elements\TextEditor\History($this->lines, $this->cursor);
    $this->cursor->modify(0, 0, 0, 0);
    $this->cursor->save();
    if (isset($state['cursor']) && is_array($state['cursor'])) {
      $this->cursor->restoreState($state['cursor']);
    }
    if (isset($state['history']) && is_array($state['history'])) {
      $this->history->restoreState($state['history']);
    }
    $this->scrollX = $state['scrollX'] ?? 0;
    $this->scrollY = $state['scrollY'] ?? 0;
    $this->preserveScrollOnNextUpdate = true;
    $this->changed = true;
    if ($this->renderer !== false) {
      $this->measure();
      $this->update();
    }
  }

  public function saveState(): array {
    return [
      'cursor' => $this->cursor->saveState(),
      'history' => $this->history->saveState(),
      'scrollX' => $this->scrollX,
      'scrollY' => $this->scrollY
    ];
  }

  public function restoreState(array $state): void {
    if (isset($state['cursor']) && is_array($state['cursor'])) {
      $this->cursor->restoreState($state['cursor']);
    }
    if (isset($state['history']) && is_array($state['history'])) {
      $this->history->restoreState($state['history']);
    }
    $this->scrollX = $state['scrollX'] ?? $this->scrollX;
    $this->scrollY = $state['scrollY'] ?? $this->scrollY;
    $this->update();
  }

  public function setHighlightRanges(array $ranges): void {
    $this->highlightRanges = [];
    foreach ($ranges as $range) {
      if (!is_array($range) || count($range) < 4) {
        continue;
      }
      $this->highlightRanges[] = [
        (int) $range[0],
        (int) $range[1],
        (int) $range[2],
        (int) $range[3]
      ];
    }
    $this->update();
  }

  public function setCursorPosition(int $row, int $col): void {
    $this->cursor->set([$row, $col, $row, $col]);
    $this->cursor->save();
    $this->update();
  }

  public function clearHighlightRanges(): void {
    $this->highlightRanges = [];
    $this->update();
  }

  public function addVariant(string $class): void {
    if ($class == 'active') {
      $this->active = true;
    }
    $this->styleColorCache = [];
    parent::addVariant($class);
    $this->update();
  }

  public function removeVariant(string $class): void {
    if ($class == 'active') {
      $this->active = false;
    }
    $this->styleColorCache = [];
    parent::removeVariant($class);
    $this->update();
  }

  protected function calculateHeights(): void {
    parent::calculateHeights();
    $maxY = count($this->lines) * $this->lineHeight + $this->geometry->borderTop + $this->geometry->paddingTop;
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent, $maxY);
  }

  protected function layout(): void {
    parent::layout();
    $maxLen = 0;
    foreach ($this->lines as $line) {
      $maxLen = max($maxLen, mb_strlen($line));
    }
    $this->geometry->contentWidth = $maxLen * $this->letterWidth;
    $this->refreshCells(false);
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

  protected function tokenize($from, $to): array {
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

  protected function screenRange(): array {
    if ($this->geometry->height == 0) {
      return [0, min(300, count($this->lines))];
    }
    if ($this->geometry->height === 'content') {
      return [0, min(300, count($this->lines))];
    }
    $first = max(0, (int)(($this->scrollY + $this->geometry->paddingTop) / $this->lineHeight));
    $rows = max(1, (int)($this->geometry->innerHeight / $this->lineHeight) + 1);
    return [$first, min($first + $rows, count($this->lines))];
  }

  protected function cursorCoordinates(array $cursor): array {
    if ($cursor[0] < $cursor[2] || ($cursor[0] == $cursor[2] && $cursor[1] <= $cursor[3])) {
      return [$cursor[0], $cursor[1], $cursor[2], $cursor[3] + 1];
    }
    return [$cursor[2], $cursor[3], $cursor[0], $cursor[1] + 1];
  }

  protected function inSelection(int $row, int $col): bool {
    [$row1, $col1, $row2, $col2] = $this->cursorCoordinates($this->cursor->get());
    if ($row < $row1 || $row > $row2) {
      return false;
    }
    if ($row === $row1 && $col < $col1) {
      return false;
    }
    if ($row === $row2 && $col >= $col2) {
      return false;
    }
    return true;
  }

  protected function inHighlight(int $row, int $col): bool {
    foreach ($this->highlightRanges as $range) {
      [$row1, $col1, $row2, $col2] = $range;
      if ($row < $row1 || $row > $row2) {
        continue;
      }
      if ($row === $row1 && $col < $col1) {
        continue;
      }
      if ($row === $row2 && $col >= $col2) {
        continue;
      }
      return true;
    }
    return false;
  }

  protected function colorsForStyle(string $styleClass, string|int $name = StyleSheet::ANY): array {
    $cacheKey = ($name === StyleSheet::ANY ? '*' : $name) . '|' . $styleClass;
    if (isset($this->styleColorCache[$cacheKey])) {
      return $this->styleColorCache[$cacheKey];
    }
    $classes = $styleClass === '' ? [] : explode(' ', $styleClass);
    $style = StyleSheet::get($this->style, $this->style, 'InputValue', $classes, $name);
    $this->styleColorCache[$cacheKey] = [
      'fg' => $style->get('color'),
      'bg' => $style->get('backgroundColor')
    ];
    return $this->styleColorCache[$cacheKey];
  }

  protected function cell(string $glyph, array $colors): array {
    return [
      'glyph' => $glyph,
      'fg' => $colors['fg'],
      'bg' => $colors['bg']
    ];
  }

  protected function appendTokenCells(array &$rowCells, int $documentRow, int &$documentCol, string $text, string $styleClass, int $firstCol, int $cols): void {
    $baseColors = $this->colorsForStyle($styleClass);
    $matchedColors = $this->colorsForStyle(trim($styleClass . ' InputValue:matched'));
    $selectedClass = $this->active ? 'InputValue:selected' : 'InputValue:inactive-selected';
    $selectedColors = $this->colorsForStyle(trim($styleClass . ' ' . $selectedClass));
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $glyph) {
      if ($documentCol >= $firstCol && count($rowCells) < $cols) {
        $colors = $baseColors;
        if ($this->inHighlight($documentRow, $documentCol)) {
          $colors = $matchedColors;
        }
        if ($this->inSelection($documentRow, $documentCol)) {
          $colors = $selectedColors;
        }
        $rowCells[] = $this->cell($glyph, $colors);
      }
      $documentCol++;
      if (count($rowCells) >= $cols) {
        return;
      }
    }
  }

  protected function buildCells(): array {
    [$firstRow, $lastRow] = $this->screenRange();
    [$firstCol, , $cols] = $this->screenColumns();
    $tokens = $this->tokenize($firstRow, $lastRow);
    $cells = [];
    $plainColors = $this->colorsForStyle('');
    $selectedClass = $this->active ? 'InputValue:selected' : 'InputValue:inactive-selected';
    $selectedColors = $this->colorsForStyle($selectedClass);
    $cursorName = is_string($this->name) ? $this->name : StyleSheet::ANY;
    $cursorClass = $this->active ? 'InputValue:cursor' : 'InputValue:inactive-cursor';
    $cursorColors = $this->colorsForStyle($cursorClass, $cursorName);
    $cursor = $this->cursor->get();
    for ($row = $firstRow; $row < $lastRow; $row++) {
      $rowCells = [];
      $documentCol = 0;
      foreach ($tokens[$row] ?? [] as $token) {
        $this->appendTokenCells($rowCells, $row, $documentCol, $token['value'], $token['style'], $firstCol, $cols);
        if (count($rowCells) >= $cols) {
          break;
        }
      }
      while (count($rowCells) < $cols) {
        $documentColForCell = $firstCol + count($rowCells);
        $colors = $plainColors;
        if ($this->inHighlight($row, $documentColForCell)) {
          $colors = $this->colorsForStyle('InputValue:matched');
        }
        if ($this->inSelection($row, $documentColForCell)) {
          $colors = $selectedColors;
        }
        $rowCells[] = $this->cell(' ', $colors);
      }
      if ($cursor[0] === $row) {
        $cursorCol = $cursor[1] - $firstCol;
        if ($cursorCol >= 0 && $cursorCol < count($rowCells)) {
          $rowCells[$cursorCol] = [
            'glyph' => $rowCells[$cursorCol]['glyph'],
            'fg' => $cursorColors['fg'],
            'bg' => $cursorColors['bg']
          ];
        }
      }
      $cells[] = $rowCells;
    }
    return $cells;
  }

  protected function update(): void {
    $this->refreshCells(true);
  }

  protected function refreshCells(bool $render): void {
    $this->cursor->save();
    if ($this->preserveScrollOnNextUpdate) {
      $this->preserveScrollOnNextUpdate = false;
      $this->clampScroll();
    } else {
      $this->setScroll();
    }
    [, $offsetX, ] = $this->screenColumns();
    $this->setCells($this->buildCells(), $offsetX);
    if ($render && $this->isVisibleInTree()) {
      Element::immediateRender($this);
    }
  }

  protected function screenColumns(): array {
    $firstPixel = max(0, $this->scrollX - $this->geometry->paddingLeft);
    $firstCol = (int)($firstPixel / $this->letterWidth);
    $offsetX = -($this->scrollX - $firstCol * $this->letterWidth);
    $drawWidth = $this->geometry->paddingLeft + $this->geometry->innerWidth + $this->geometry->paddingRight - $offsetX;
    $cols = max(1, (int)ceil($drawWidth / $this->letterWidth) + 1);
    return [$firstCol, $offsetX, $cols];
  }

  protected function isVisibleInTree(): bool {
    $element = $this;
    while ($element !== null) {
      if (!$element->isDisplayed()) {
        return false;
      }
      $element = $element->getAncestor();
    }
    return true;
  }

  protected function setScroll(): void {
    $cursor = $this->cursor->get();
    $row = $cursor[0];
    $col = $cursor[1];
    $rowTop = $row * $this->lineHeight;
    $rowBottom = $rowTop + $this->lineHeight;
    if ($rowTop < $this->scrollY) {
      $this->scrollY = $rowTop;
    } else if ($rowBottom > $this->scrollY + $this->geometry->innerHeight) {
      $this->scrollY = $rowBottom - $this->geometry->innerHeight;
    }
    $colLeft = $col * $this->letterWidth;
    $colRight = $colLeft + $this->letterWidth;
    if ($colLeft < $this->scrollX) {
      $this->scrollX = $colLeft;
    } else if ($colRight > $this->scrollX + $this->geometry->innerWidth) {
      $this->scrollX = $colRight - $this->geometry->innerWidth;
    }
    $maxScrollX = max(0, $this->geometry->contentWidth - $this->geometry->innerWidth);
    $this->scrollX = max(0, $this->scrollX);
    $this->scrollX = min($this->scrollX, $maxScrollX);
    $this->scrollY = max(0, $this->scrollY);
  }

  protected function clampScroll(): void {
    $maxScrollX = max(0, $this->geometry->contentWidth - $this->geometry->innerWidth);
    $this->scrollX = max(0, min($this->scrollX, $maxScrollX));
    $this->scrollY = max(0, $this->scrollY);
  }

  public function insertText(string $text): void {
    $this->highlightRanges = [];
    $lines = explode("\n", $text);
    $n = count($lines);
    $len = mb_strlen(end($lines));
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    if ($n === 1) {
      $this->cursor->modify($row1, $col1 + $len, $row1, $col1 + $len);
    } else {
      $this->cursor->modify($row1 + $n - 1, $len, $row1 + $n - 1, $len);
    }
    $this->replaceSelection($lines);
    $this->update();
  }

  public function replaceText(string $text): void {
    $this->highlightRanges = [];
    $replacement = explode("\n", $text);
    $last = count($replacement) - 1;
    $lastLen = mb_strlen($replacement[$last]);
    $this->cursor->modify($last, $lastLen, $last, $lastLen);
    $this->lineSplice(0, count($this->lines), $replacement);
    $this->update();
  }

  protected function lineSplice($offset, $length, $replacement): void {
    $this->history->store($offset, $length, $replacement);
    array_splice($this->lines, $offset, $length, $replacement);
    $this->invalidateTokensFrom($offset);
  }

  protected function clearSelection(): void {
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[$row1], 0, $col1);
    $after = mb_substr($this->lines[$row2], $col2);
    $this->lineSplice($row1, $row2 - $row1 + 1, [$before . $after]);
  }

  protected function replaceSelection($newLines): void {
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
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $linesOnScreen = (int)($this->geometry->height / $this->lineHeight) - 1;
    $lettersOnScreen = (int)($this->geometry->innerWidth / $this->letterWidth);
    $handled = $this->cursor->handleKeys($keycombo, $linesOnScreen, $lettersOnScreen);
    if ($handled) {
      $this->update();
      return true;
    }
    switch ($keycombo) {
      case Action::SELECT_ITEM:
        return true;
      case Action::DO_IT:
        $this->highlightRanges = [];
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        if ($row1 === $row2 && $col1 === $col2 - 1) {
          $col2 = $col1;
        }
        $before = mb_substr($this->lines[$row1], 0, $col1);
        $after = mb_substr($this->lines[$row2], $col2);
        $this->lineSplice($row1, $row2 - $row1 + 1, [$before, $after]);
        $this->cursor->modify($row1 + 1, 0, $row1 + 1, 0);
        break;
      case Action::DELETE_BACK:
        $this->highlightRanges = [];
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
        $this->highlightRanges = [];
        $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
        $line = $this->lines[$row1];
        $len = mb_strlen($line);
        if ($row1 === $row2 && $col1 === $len && $col2 === $len + 1) {
          if ($row1 < count($this->lines) - 1) {
            $line2 = $this->lines[$row1 + 1];
            $this->cursor->resetSelection();
            $this->lineSplice($row1, 2, [$line . $line2]);
          }
        } else {
          $this->cursor->modify($row1, $col1, $row1, $col1);
          $this->clearSelection();
        }
        break;
      case Action::CUT:
        $this->highlightRanges = [];
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
          $this->insertText($paste);
          return true;
        }
        break;
      case Action::UNDO:
        $this->highlightRanges = [];
        $this->history->undo();
        $this->invalidateTokensFrom(0);
        break;
      case Action::REDO:
        $this->highlightRanges = [];
        $this->history->redo();
        $this->invalidateTokensFrom(0);
        break;
      default:
        return false;
    }
    $this->update();
    return true;
  }

  public function textInputHandler($element, $event) {
    $this->highlightRanges = [];
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $this->cursor->modify($row1, $col1 + 1, $row1, $col1 + 1);
    $this->replaceSelection([$event['text']]);
    $this->update();
    return true;
  }

}
