<?php

namespace SPTK\Widgets\RadioButton;

use SPTK\Core\Style;
use SPTK\Widgets\Choice\Choice;

/** Presents one checked item from a scrollable radio group. */
final class RadioButton extends Choice {

  /** Create a single-selection choice group. */
  public function __construct(array $items = [], Style $style = new Style(), ?string $title = null) {
    parent::__construct(false, $items, $style, $title);
  }

  /** Return the selected value, or null when the group is empty. */
  public function getValue(): ?string {
    return $this->checked[0] ?? null;
  }

  /** Select an existing value without emitting a user change event. */
  public function setValue(string $value): void {
    $this->setChecked([$value]);
  }

}
