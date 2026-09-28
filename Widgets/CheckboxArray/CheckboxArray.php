<?php

namespace SPTK\Widgets\CheckboxArray;

use SPTK\Core\{Color, Style};
use SPTK\Widgets\Choice\Choice;

/** Presents independently checked items in a scrollable choice group. */
final class CheckboxArray extends Choice {

  /** Create a multiple-selection choice group. */
  public function __construct(array $items = [], Style $style = new Style(background: new Color(0, 0, 0), foreground: new Color(255, 255, 255), cursorBackground: new Color(85, 85, 85))) {
    parent::__construct(true, $items, $style);
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
