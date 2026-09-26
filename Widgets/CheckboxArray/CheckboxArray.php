<?php

namespace SPTK\Widgets\CheckboxArray;

use SPTK\Core\Color;
use SPTK\Widgets\Choice\Choice;

/** Presents independently checked items in a scrollable choice group. */
final class CheckboxArray extends Choice {

  /** Create a multiple-selection choice group. */
  public function __construct(array $items = [], Color $fg = new Color(255, 255, 255), Color $bg = new Color(0, 0, 0), Color $cursorBg = new Color(85, 85, 85), Color $highlight = new Color(0, 255, 255)) {
    parent::__construct(true, $items, $fg, $bg, $cursorBg, $highlight);
  }

  /** Return checked values in display order. */
  public function getValue(): array {
    return $this->checked;
  }

  /** Replace checked values atomically without emitting a user change event. */
  public function setValue(array $values): void {
    $this->setChecked($values);
  }

}
