<?php

namespace SPTK\Core;

/** A glyph and its colors; width 0 is the second cell of a width-2 glyph. */
final readonly class Cell {

  public function __construct(
    public string $glyph = ' ',
    public Color $fg = new Color(255, 255, 255),
    public Color $bg = new Color(0, 0, 0),
    public int $width = 1,
  ) {
    if (!in_array($width, [0, 1, 2], true) || ($width === 0 && $glyph !== '')) {
      throw new \InvalidArgumentException('Cell width must be 0, 1, or 2; continuations have no glyph.');
    }
  }

}
