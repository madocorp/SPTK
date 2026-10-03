<?php

namespace SPTK\Core;

/** Stores text and cursor snapshots for undo and redo, grouping short typing runs. */
final class TextHistory {

  private array $undo = [];
  private array $redo = [];
  private float $lastChange = 0.0;

  /** Record one change and merge adjacent same-line typing within half a second. */
  public function record(array $before, array $beforeCursor, array $after, array $afterCursor, ?int $typingRow = null): void {
    if ($before === $after) {
      return;
    }
    $this->redo = [];
    $now = microtime(true);
    $last = count($this->undo) - 1;
    if ($typingRow !== null && $last >= 0 && $this->undo[$last]['typingRow'] === $typingRow
      && $this->undo[$last]['after'] === $before && $this->undo[$last]['afterCursor'] === $beforeCursor
      && $now - $this->lastChange <= 0.5) {
      $this->undo[$last]['after'] = $after;
      $this->undo[$last]['afterCursor'] = $afterCursor;
      $this->lastChange = $now;
      return;
    }
    $this->undo[] = compact('before', 'beforeCursor', 'after', 'afterCursor', 'typingRow');
    $this->lastChange = $now;
  }

  /** Restore the state before the latest change. */
  public function undo(array &$lines): ?array {
    if ($this->undo === []) {
      return null;
    }
    $state = array_pop($this->undo);
    $this->redo[] = $state;
    $lines = $state['before'];
    return $state['beforeCursor'];
  }

  /** Restore the state after the next change. */
  public function redo(array &$lines): ?array {
    if ($this->redo === []) {
      return null;
    }
    $state = array_pop($this->redo);
    $this->undo[] = $state;
    $lines = $state['after'];
    return $state['afterCursor'];
  }

}
