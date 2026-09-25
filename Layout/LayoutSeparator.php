<?php

namespace SPTK\Layout;

use SPTK\Core\Color;
use SPTK\Rendering\PixelRenderer;

/** Marks a split boundary where the screen should draw a separator line. */
final class LayoutSeparator {

  private ?Tile $area = null;

  public function setArea(Tile $area): void {
    $this->area = $area;
  }

  public function draw(PixelRenderer $renderer, Color $color): void {
    if ($this->area === null) {
      throw new \LogicException('Separator area has not been measured.');
    }
    $renderer->fill($this->area, $color);
  }

  public function debug(int $level = 0): void {
    echo str_repeat('  ', $level) . "Separator\n";
  }

}
