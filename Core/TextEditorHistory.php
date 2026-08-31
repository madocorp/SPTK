<?php

namespace SPTK\Core;

/**
 * Stores editor snapshots for undo/redo, grouping short same-line typing runs.
 */
class TextEditorHistory {

  protected const TYPE_TIMEOUT = 0.5;

  protected array $undo = [];
  protected array $redo = [];
  protected float $lastChange = 0.0;

  public function record(array $beforeLines, array $beforeCursor, array $afterLines, array $afterCursor, ?int $groupRow = null): void {
    if ($beforeLines === $afterLines && $beforeCursor === $afterCursor) {
      return;
    }
    $this->redo = [];
    $now = microtime(true);
    $last = count($this->undo) - 1;
    if (
      $groupRow !== null &&
      $last >= 0 &&
      ($this->undo[$last]['groupRow'] ?? null) === $groupRow &&
      $now - $this->lastChange <= self::TYPE_TIMEOUT
    ) {
      $this->undo[$last]['afterLines'] = $afterLines;
      $this->undo[$last]['afterCursor'] = $afterCursor;
      $this->lastChange = $now;
      return;
    }
    $this->undo[] = [
      'beforeLines' => $beforeLines,
      'beforeCursor' => $beforeCursor,
      'afterLines' => $afterLines,
      'afterCursor' => $afterCursor,
      'groupRow' => $groupRow,
    ];
    $this->lastChange = $now;
  }

  public function undo(array &$lines): ?array {
    if (empty($this->undo)) {
      return null;
    }
    $state = array_pop($this->undo);
    $this->redo[] = $state;
    $lines = $state['beforeLines'];
    return $state['beforeCursor'];
  }

  public function redo(array &$lines): ?array {
    if (empty($this->redo)) {
      return null;
    }
    $state = array_pop($this->redo);
    $this->undo[] = $state;
    $lines = $state['afterLines'];
    return $state['afterCursor'];
  }

  public function saveState(): array {
    return [
      'undo' => $this->undo,
      'redo' => $this->redo,
      'lastChange' => $this->lastChange,
    ];
  }

  public function restoreState(array $state): void {
    $this->undo = $state['undo'] ?? [];
    $this->redo = $state['redo'] ?? [];
    $this->lastChange = $state['lastChange'] ?? 0.0;
  }

}
