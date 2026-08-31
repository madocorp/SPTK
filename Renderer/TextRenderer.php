<?php

namespace SPTK\Renderer;

use SPTK\Core\GridBuffer;

/**
 * Serializes a grid buffer to plain text for headless examples and tests.
 */
class TextRenderer {

  public function render(GridBuffer $buffer): string {
    return implode("\n", $buffer->lines()) . "\n";
  }

}
