<?php

namespace SPTK\Elements\TextEditor;

class History {

  const TYPE_TIMEOUT = 500000;

  private array $lines;
  private Cursor $cursor;
  private array $undo = [];
  private array $redo = [];
  private array|false $lineUnderConstruction = false;
  private float $lastChange = 0.0;

  public function __construct(array &$lines, Cursor $cursor) {
    $this->lines = &$lines;
    $this->cursor = $cursor;
  }

  private function startEditingLine(int $row): void {
    $this->lineUnderConstruction = [
      'offset' => $row, 
      'linesBefore' => [$this->lines[$row]],
      'linesAfter' => false, 
      'cursorBefore' => $this->cursor->getBefore(),
      'cursorAfter' => false
    ];
    $this->lastChange = microtime(true);
  }

  private function saveEditedLine(): void {
    $row = $this->lineUnderConstruction['offset'];
    $this->lineUnderConstruction['linesAfter'] = [$this->lines[$row]];
    $this->lineUnderConstruction['cursorAfter'] = $this->cursor->getBefore();
    $this->undo[] = $this->lineUnderConstruction;
    $this->lineUnderConstruction = false;
  }

  public function differsByAtMostOneChar(string $a, string $b): bool {
    $arrA = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
    $arrB = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
    $lenA = count($arrA);
    $lenB = count($arrB);
    if (abs($lenA - $lenB) > 1) {
      return false;
    }
    if ($lenA === $lenB) {
      $diff = 0;
      for ($i = 0; $i < $lenA; $i++) {
        if ($arrA[$i] !== $arrB[$i] && ++$diff > 1) return false;
      }
      return true;
    }
    if ($lenA > $lenB) {
      [$arrA, $arrB, $lenA, $lenB] = [$arrB, $arrA, $lenB, $lenA];
    }
    $i = $j = $diff = 0;
    while ($i < $lenA && $j < $lenB) {
      if ($arrA[$i] === $arrB[$j]) {
        $i++;
        $j++;
      } else {
        if (++$diff > 1) return false;
        $j++;
      }
    }
    return true;
  }

  public function store(int $offset, int $length, array $replacement): void {
    $this->redo = [];
    if ($length === 1 && count($replacement) === 1) {
      $shortDiff = $this->differsByAtMostOneChar($this->lines[$offset], $replacement[0]);
      if ($shortDiff) {
        if ($this->lineUnderConstruction !== false) {
          $now = microtime(true);
          $timeDiff = ($now - $this->lastChange) * 1000000;
          $this->lastChange = $now;
          if ($timeDiff > self::TYPE_TIMEOUT) {
            $this->saveEditedLine();
          }
        }
        if ($this->lineUnderConstruction === false) {
          $this->startEditingLine($offset);
        } else if ($this->lineUnderConstruction['offset'] !== $offset) {
          $this->saveEditedLine();
          $this->startEditingLine($offset);
        }
        return;
      }
    }
    if ($this->lineUnderConstruction !== false) {
      $this->saveEditedLine();
    }
    $original = array_slice($this->lines, $offset, $length);
    $this->undo[] = [
      'offset' => $offset,
      'linesBefore' => $original,
      'linesAfter' => $replacement, 
      'cursorBefore' => $this->cursor->getBefore(),
      'cursorAfter' => $this->cursor->get()
    ];
  }

  public function undo(): void {
    if ($this->lineUnderConstruction !== false) {
      $this->saveEditedLine();
    }
    if (empty($this->undo)) {
      return;
    }
    $state = array_pop($this->undo);
    $this->redo[] = $state;
    $offset = $state['offset'];
    $length = count($state['linesAfter']);
    $replacement = $state['linesBefore'];
    $cursor = $state['cursorBefore'];
    array_splice($this->lines, $offset, $length, $replacement);
    $this->cursor->set($cursor);
  }

  public function redo(): void {
    if (empty($this->redo)) {
      return;
    }
    $state = array_pop($this->redo);
    $this->undo[] = $state;
    $offset = $state['offset'];
    $length = count($state['linesBefore']);
    $replacement = $state['linesAfter'];
    $cursor = $state['cursorAfter'];
    array_splice($this->lines, $offset, $length, $replacement);
    $this->cursor->set($cursor);
  }

}
