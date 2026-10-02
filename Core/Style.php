<?php

namespace SPTK\Core;

/** Holds inherited colors and item-owned pixel box measurements. */
final readonly class Style {

  public function __construct(
    public Color $background = new Color(32, 38, 48),
    public Color $foreground = new Color(237, 241, 245),
    public Color $separator = new Color(71, 85, 104),
    public Color $highlight = new Color(128, 203, 196),
    public Color $selected = new Color(255, 209, 128),
    public Color $cursorBackground = new Color(82, 101, 121),
    public Color $cursorForeground = new Color(255, 255, 255),
    public Color $error = new Color(255, 110, 110),
    public Color $borderColor = new Color(71, 85, 104),
    public int|string|array $borderWidth = 0,
    public int|string|array $margin = 0,
    public int|string|array $padding = 0,
  ) {
  }

  /** Return a style with the supplied color or box overrides. */
  public function with(array $values): self {
    return new self(
      $values['Background'] ?? $this->background,
      $values['Foreground'] ?? $this->foreground,
      $values['Separator'] ?? $this->separator,
      $values['Highlight'] ?? $this->highlight,
      $values['Selected'] ?? $this->selected,
      $values['CursorBackground'] ?? $this->cursorBackground,
      $values['CursorForeground'] ?? $this->cursorForeground,
      $values['Error'] ?? $this->error,
      $values['BorderColor'] ?? $values['Separator'] ?? $this->borderColor,
      $values['BorderWidth'] ?? $this->borderWidth,
      $values['Margin'] ?? $this->margin,
      $values['Padding'] ?? $this->padding,
    );
  }

  /** Inherit colors into a child without repeating the parent's outer box edges. */
  public function forChild(): self {
    return new self(
      $this->background,
      $this->foreground,
      $this->separator,
      $this->highlight,
      $this->selected,
      $this->cursorBackground,
      $this->cursorForeground,
      $this->error,
      $this->borderColor,
    );
  }

}
