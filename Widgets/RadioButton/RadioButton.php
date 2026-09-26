<?php

namespace SPTK\Widgets\RadioButton;

use SPTK\Core\Color;
use SPTK\Widgets\Choice\Choice;

/** Presents one checked item from a scrollable radio group. */
final class RadioButton extends Choice {

  /** Create a single-selection choice group. */
  public function __construct(array $items = [], Color $fg = new Color(255, 255, 255), Color $bg = new Color(0, 0, 0), Color $cursorBg = new Color(85, 85, 85), Color $highlight = new Color(0, 255, 255)) {
    parent::__construct(false, $items, $fg, $bg, $cursorBg, $highlight);
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
