<?php

namespace SPTK\Core;

/**
 * Represents one character-grid cell with glyph, colors, and style flags.
 */
class Cell {

  public string $glyph;
  public Color $fg;
  public Color $bg;
  public array $flags;

  public function __construct(
    string $glyph = ' ',
    string|int|Color|null $fg = null,
    string|int|Color|null $bg = null,
    array $flags = []
  ) {
    if ($glyph === '') {
      $glyph = ' ';
    }
    $this->glyph = mb_substr($glyph, 0, 1);
    $this->fg = Color::from($fg ?? '#ffffff');
    $this->bg = Color::from($bg ?? '#000000');
    $this->flags = $flags;
  }

  public function copy(): self {
    return new self($this->glyph, $this->fg, $this->bg, $this->flags);
  }

}
