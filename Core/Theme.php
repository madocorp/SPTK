<?php

namespace SPTK\Core;

/**
 * Groups the basic colors shared by grid elements during rendering.
 */
class Theme {

  public Color $fg;
  public Color $bg;
  public Color $accent;
  public Color $muted;
  public Color $inverseFg;
  public Color $inverseBg;
  public Color $border;
  public Color $hotkey;
  public Color $markerFg;
  public Color $menuActiveBg;
  public Color $menuPopupFg;
  public Color $menuPopupBg;
  public Color $menuPopupActiveFg;
  public Color $menuPopupActiveBg;
  public Color $cursorFg;
  public Color $cursorBg;
  public Color $inactiveCursorFg;
  public Color $inactiveCursorBg;
  public int $rowHeight;

  public function __construct(
    string|int|Color $fg = '#aaaaaa',
    string|int|Color $bg = '#0000aa',
    string|int|Color $accent = '#00ffff',
    string|int|Color $muted = '#777777',
    string|int|Color $inverseFg = '#000000',
    string|int|Color $inverseBg = '#00aaaa',
    string|int|Color $border = '#00ffff',
    string|int|Color $hotkey = '#0000ff',
    string|int|Color $markerFg = '#00ffff',
    string|int|Color $menuActiveBg = '#00ffff',
    string|int|Color $menuPopupFg = '#ffffff',
    string|int|Color $menuPopupBg = '#00aaaa',
    string|int|Color $menuPopupActiveFg = '#ffffff',
    string|int|Color $menuPopupActiveBg = '#000000',
    string|int|Color $cursorFg = '#ffffff',
    string|int|Color $cursorBg = '#000000',
    string|int|Color $inactiveCursorFg = '#cccccc',
    string|int|Color $inactiveCursorBg = '#555555',
    int $rowHeight = 23
  ) {
    $this->fg = Color::from($fg);
    $this->bg = Color::from($bg);
    $this->accent = Color::from($accent);
    $this->muted = Color::from($muted);
    $this->inverseFg = Color::from($inverseFg);
    $this->inverseBg = Color::from($inverseBg);
    $this->border = Color::from($border);
    $this->hotkey = Color::from($hotkey);
    $this->markerFg = Color::from($markerFg);
    $this->menuActiveBg = Color::from($menuActiveBg);
    $this->menuPopupFg = Color::from($menuPopupFg);
    $this->menuPopupBg = Color::from($menuPopupBg);
    $this->menuPopupActiveFg = Color::from($menuPopupActiveFg);
    $this->menuPopupActiveBg = Color::from($menuPopupActiveBg);
    $this->cursorFg = Color::from($cursorFg);
    $this->cursorBg = Color::from($cursorBg);
    $this->inactiveCursorFg = Color::from($inactiveCursorFg);
    $this->inactiveCursorBg = Color::from($inactiveCursorBg);
    $this->rowHeight = max(1, $rowHeight);
  }

}
