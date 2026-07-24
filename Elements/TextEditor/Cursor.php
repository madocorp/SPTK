<?php

namespace SPTK\Elements\TextEditor;

use \SPTK\SDLWrapper\Action;

class Cursor {

  protected array $lines;
  protected int $longestLine = 0;
  protected array $caret = [0, 0];
  protected array $anchor = [0, 0];
  protected array $caretBefore = [0, 0];
  protected array $anchorBefore = [0, 0];
  protected int|false $preferredCol = false;
  protected bool $freeSelectionMode = false;

  public function __construct(array &$lines) {
    $this->lines = &$lines;
  }

  protected function getLineLength(int $i): int {
    if ($this->freeSelectionMode) {
      $this->longestLine = 0;
      foreach ($this->lines as $line) {
        $this->longestLine = max($this->longestLine, mb_strlen($line));
      }
      return $this->longestLine;
    }
    return mb_strlen($this->lines[$i]);
  }

  protected function getLineCount(): int {
    return count($this->lines);
  }

  protected function checkDocStart(): void {
    $this->caret[0] = max(0, $this->caret[0]);
  }

  protected function checkDocEnd(): void {
    $lcnt = $this->getLineCount();
    $this->caret[0] = min($lcnt - 1, $this->caret[0]);
  }

  protected function checkLineLength(): void {
    $len = $this->getLineLength($this->caret[0]);
    $this->caret[1] = min($len, $this->caret[1]);
  }

  protected function resetPreferredColumn(): void {
    $this->preferredCol = $this->caret[1];
  }

  protected function moveVertical(int $rows, bool $select = false): void {
    if ($this->preferredCol === false) {
      $this->preferredCol = $this->caret[1];
    }
    $this->caret[0] += $rows;
    $this->checkDocStart();
    $this->checkDocEnd();
    $this->caret[1] = $this->preferredCol;
    $this->checkLineLength();
    $this->resetSelection($select);
  }

  public function save(): void {
    $this->caretBefore = $this->caret;
    $this->anchorBefore = $this->anchor;
  }

  public function get(): array {
    return [$this->caret[0], $this->caret[1], $this->anchor[0], $this->anchor[1]];
  }

  public function getBefore(): array {
    return [$this->caretBefore[0], $this->caretBefore[1], $this->anchorBefore[0], $this->anchorBefore[1]];
  }

  public function toCoordinates(?int &$row1, ?int &$col1, ?int &$row2, ?int &$col2): void {
    $caret = $this->caretBefore;
    $anchor = $this->anchorBefore;
    if ($this->freeSelectionMode) {
      $row1 = min($caret[0], $anchor[0]);
      $row2 = max($caret[0], $anchor[0]);
      $col1 = min($caret[1], $anchor[1]);
      $col2 = max($caret[1] + 1, $anchor[1] + 1);
    } else if (
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

  public function set(array $cursor): void {
    $this->caret[0] = $cursor[0];
    $this->caret[1] = $cursor[1];
    $this->anchor[0] = $cursor[2];
    $this->anchor[1] = $cursor[3];
    $this->resetPreferredColumn();
  }

  public function saveState(): array {
    return [
      'caret' => $this->caret,
      'anchor' => $this->anchor,
      'caretBefore' => $this->caretBefore,
      'anchorBefore' => $this->anchorBefore,
      'preferredCol' => $this->preferredCol,
      'freeSelectionMode' => $this->freeSelectionMode
    ];
  }

  public function restoreState(array $state): void {
    $this->caret = $state['caret'] ?? [0, 0];
    $this->anchor = $state['anchor'] ?? $this->caret;
    $this->caretBefore = $state['caretBefore'] ?? $this->caret;
    $this->anchorBefore = $state['anchorBefore'] ?? $this->anchor;
    $this->preferredCol = $state['preferredCol'] ?? $this->caret[1];
    $this->freeSelectionMode = $state['freeSelectionMode'] ?? false;
  }

  public function modify(int|false $caretRow, int|false $caretCol, int|false $anchorRow, int|false $anchorCol): void {
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
    $this->resetPreferredColumn();
  }

  public function getSelection(): string {
    $this->toCoordinates($row1, $col1, $row2, $col2);
    $lines = [];
    for ($i = $row1; $i <= $row2; $i++) {
      $line = $this->lines[$i];
      if ($this->freeSelectionMode) {
        $line = mb_substr($line, min($col1, $col2), abs($col2 - $col1));
      } else {
        if ($i === $row2) {
          $line = mb_substr($line, 0, $col2);
        }
        if ($i === $row1) {
          $line = mb_substr($line, $col1);
        }
      }
      $lines[] = $line;
    }
    return implode("\n", $lines);
  }

  public function resetSelection(bool $select = false): void {
    if ($select) {
      return;
    }
    $this->anchor[0] = $this->caret[0];
    $this->anchor[1] = $this->caret[1];
  }

  public function freeSelection(?bool $mode = null): bool {
    if ($mode !== null) {
      $this->freeSelectionMode = $mode;
    }
    return $this->freeSelectionMode;
  }

  public function moveUp(bool $select = false): void {
    $this->moveVertical(-1, $select);
  }

  public function movePageUp(int $linesOnScreen, bool $select = false): void {
    $this->moveVertical(-$linesOnScreen, $select);
  }

  public function moveDocStart(bool $select = false): void {
    $this->caret[0] = 0;
    $this->caret[1] = 0;
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveDown(bool $select = false): void {
    $this->moveVertical(1, $select);
  }

  public function movePageDown(int $linesOnScreen, bool $select = false): void {
    $this->moveVertical($linesOnScreen, $select);
  }

  public function moveDocEnd(bool $select = false): void {
    $lines = $this->getLineCount() - 1;
    $this->caret[0] = $lines;
    $this->caret[1] = $this->getLineLength($lines);
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveForward(bool $select = false): void {
    $len = $this->getLineLength($this->caret[0]);
    if ($this->caret[1] < $len) {
      $this->caret[1]++;
    } else {
      $lcnt = $this->getLineCount();
      if ($this->caret[0] < $lcnt - 1) {
        $this->caret[0]++;
        $this->caret[1] = 0;
      }
    }
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveScreenEnd(int $lettersOnScreen, bool $select = false): void {
    $this->caret[1] += $lettersOnScreen;
    $this->checkLineLength();
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveLineEnd(bool $select = false): void {
    $this->caret[1] = $this->getLineLength($this->caret[0]);
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveBackward(bool $select = false): void {
    if ($this->caret[1] > 0) {
      $this->caret[1]--;
    } else {
      if ($this->caret[0] > 0) {
        $this->caret[0]--;
        $this->caret[1] = $this->getLineLength($this->caret[0]);
      }
    }
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveScreenStart(int $lettersOnScreen, bool $select = false): void {
    $this->caret[1] = max(0, $this->caret[1] - $lettersOnScreen);
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function moveLineStart(bool $select = false): void {
    $this->caret[1] = 0;
    $this->resetPreferredColumn();
    $this->resetSelection($select);
  }

  public function handleKeys(Action|int $keycombo, int $linesOnScreen, int $lettersOnScreen): bool {
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
