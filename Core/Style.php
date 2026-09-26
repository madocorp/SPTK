<?php

namespace SPTK\Core;

/** Holds inherited application colors for widgets and layout decoration. */
final readonly class Style {

  public function __construct(
    public Color $background = new Color(0, 0, 0),
    public Color $foreground = new Color(255, 255, 255),
    public Color $separator = new Color(170, 170, 170),
    public Color $highlight = new Color(0, 255, 255),
    public Color $selected = new Color(255, 255, 255),
    public Color $cursorBackground = new Color(85, 85, 85),
    public Color $cursorForeground = new Color(255, 255, 255)
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
      $colors['CursorForeground'] ?? $this->cursorForeground
    );
  }

}
