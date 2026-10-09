<?php

namespace SPTK\Layout;

use SPTK\Core\Color;
use SPTK\Rendering\PixelRenderer;

/** Draws a layout's separators with its inherited or locally overridden color. */
trait LayoutNodeSeparators {

  private ?Color $separatorColor = null;

  /** Override the separator color for this layout and its descendants. */
  public function setSeparatorColor(Color $color): void {
    $this->separatorColor = $color;
  }

  /** Draw separators after content so their lines remain visible. */
  public function drawSeparators(PixelRenderer $renderer, Color $color): void {
    if ($this->pixelTile !== null) {
      return;
    }
    $color = $this->separatorColor ?? $color;
    foreach ($this->children as $child) {
      if ($child instanceof LayoutNode) {
        $child->drawSeparators($renderer, $color);
      }
    }
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        $child->draw($renderer, $color);
      }
    }
  }

}
