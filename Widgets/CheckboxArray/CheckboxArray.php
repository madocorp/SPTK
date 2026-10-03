<?php

namespace SPTK\Widgets\CheckboxArray;

use SPTK\Core\Style;
use SPTK\Widgets\Choice\Choice;

/** Presents independently checked items in a scrollable choice group. */
final class CheckboxArray extends Choice {

  /** Create a multiple-selection choice group. */
  public function __construct(array $items = [], Style $style = new Style(), ?string $title = null) {
    parent::__construct(true, $items, $style, $title);
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
