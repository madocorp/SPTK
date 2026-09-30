<?php

namespace SPTK\Core;

/** Holds inherited application colors for widgets and layout decoration. */
final readonly class Style {

  public function __construct(
    public Color $background = new Color(32, 38, 48),
    public Color $foreground = new Color(237, 241, 245),
    public Color $separator = new Color(71, 85, 104),
    public Color $highlight = new Color(128, 203, 196),
    public Color $selected = new Color(255, 209, 128),
    public Color $cursorBackground = new Color(82, 101, 121),
    public Color $cursorForeground = new Color(255, 255, 255),
    public Color $error = new Color(255, 110, 110)
  ) {
  }

  /** Return a style with the supplied color overrides. */
  public function with(array $colors): self {
    return new self(
      $colors['Background'] ?? $this->background,
      $colors['Foreground'] ?? $this->foreground,
      $colors['Separator'] ?? $this->separator,
      $colors['Highlight'] ?? $this->highlight,
      $colors['Selected'] ?? $this->selected,
      $colors['CursorBackground'] ?? $this->cursorBackground,
      $colors['CursorForeground'] ?? $this->cursorForeground,
      $colors['Error'] ?? $this->error
    );
  }

}
