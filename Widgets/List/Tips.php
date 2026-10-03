<?php

namespace SPTK\Widgets\List;

/** Supplies list tips from selection, search, filter, and reordering capabilities. */
trait Tips {

  /** Describe the available list controls for the current activation state. */
  protected function defaultTip(bool $active): string {
    if (!$active) {
      return 'Return opens list. Arrow keys move between tiles.';
    }
    $tip = 'Up/Down selects an item';
    if ($this->multiple) {
      $tip .= '; Space toggles it';
    }
    if ($this->reorderable) {
      $tip .= '; Shift+Up/Down reorders';
    }
    if ($this->filterable || $this->searchable) {
      $tip .= '; type to find';
    }
    return $tip . '; Esc finishes.';
  }

}
