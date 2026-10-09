<?php

namespace SPTK\Core;

use SPTK\Rendering\GridWriter;
use SPTK\Rendering\PixelRenderer;
use SPTK\Layout\Tile;

/** Base behavior shared by widgets that measure themselves and paint into a tile. */
abstract class Widget {

  private ?string $id = null;
  private ?string $tipOverride = null;
  private ?string $activeTipOverride = null;

  /** Assign the optional XML identifier. */
  public function setId(?string $id): void {
    $this->id = $id;
  }

  /** Return the optional XML identifier. */
  public function id(): ?string {
    return $this->id;
  }

  /** Override focus and optional active tips with printable XML or application text. */
  public function setTips(?string $tip, ?string $activeTip = null): void {
    foreach ([$tip, $activeTip] as $value) {
      if ($value !== null && (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $value))) {
        throw new \InvalidArgumentException('Widget tip must be printable single-line UTF-8 text.');
      }
    }
    $this->tipOverride = $tip;
    $this->activeTipOverride = $activeTip;
  }

  /** Return the current focus or active tip, applying any explicit override. */
  public function tip(bool $active = false): string {
    return ($active ? $this->activeTipOverride : null) ?? $this->tipOverride ?? $this->defaultTip($active);
  }

  /** Supply a generic keyboard tip when a widget has no specialized behavior. */
  protected function defaultTip(bool $active): string {
    return $active ? 'Escape or Return finishes. Arrow keys act inside this widget.' : ($this->canActivate() ? 'Return activates this widget. Arrow keys move between tiles.' : 'Arrow keys move between tiles.');
  }

  /** Subscribe a named handler to an event emitted by this widget. */
  abstract public function on(string $event, callable $listener): void;

  /** Emit an event to this widget's registered handlers. */
  abstract public function emit(string $event): void;

  /** Handle a raw input event while this widget is activated. */
  abstract public function handleInput(mixed $event): bool;

  /** Return the color used to fill the widget's tile. */
  abstract public function background(): Color;

  /** Allow grid widgets to retain their padding color when not selected. */
  public function dimBackgroundWhenUnselected(): bool {
    return true;
  }

  /** Keep persistent messages bright even while another tile has keyboard focus. */
  public function dimContentWhenUnselected(): bool {
    return true;
  }

  /** Paint the widget into its allocated tile. */
  abstract public function paint(GridWriter $writer): void;

  /** Paint only pending cells when the widget supports a smaller update. */
  public function paintUpdate(GridWriter $writer): bool {
    return false;
  }

  /** Paint optional pixel content over the completed character grid. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
  }

  /** Paint through an explicit clip, preserving older pixel widgets by default. */
  public function paintClippedPixels(PixelRenderer $renderer, Tile $area, Tile $clip, bool $selected): void {
    $this->paintPixels($renderer, $area, $selected);
  }

  /** Keep pixel content inside an overflow viewport when the widget supports clipping. */
  public function clipsPixelViewport(): bool {
    return false;
  }

  /** Draw text glyphs again above this widget's pixel content. */
  public function pixelBehindText(): bool {
    return false;
  }

  /** Keep pixel content inside the cell area, leaving the surrounding layout padding visible. */
  public function pixelPadding(): bool {
    return true;
  }

  /** Choose the pixel drawing area from the cell tile and its padded background. */
  public function pixelArea(Tile $cellArea, Tile $backgroundArea): Tile {
    return $this->pixelPadding() ? $cellArea : $backgroundArea;
  }

  /** Report whether the widget draws pixels beyond its character cells. */
  public function paintsPixels(): bool {
    return false;
  }

  /** Report whether Return may put this widget into input mode. */
  public function canActivate(): bool {
    return true;
  }

  /** Choose the notification that releases this widget for a key, or keep it active. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return match ($key) {
      \SPTK\SDLWrapper\SDL::KEY_RETURN => 'accept',
      \SPTK\SDLWrapper\SDL::KEY_ESCAPE => 'cancel',
      default => null,
    };
  }

  /** Return the preferred width in grid cells, or null when it has no preference. */
  public function preferredWidth(): ?int {
    return null;
  }

  /** Return the preferred height in grid cells, or null when it has no preference. */
  public function preferredHeight(): ?int {
    return null;
  }

  /** Return an exact pixel width when a pixel layout asks for intrinsic sizing. */
  public function preferredPixelWidth(): ?int {
    $width = $this->preferredWidth();
    return $width === null ? null : $width * (\SPTK\App::fontOrNull()?->cellWidth() ?? 8);
  }

  /** Return an exact pixel height when a pixel layout asks for intrinsic sizing. */
  public function preferredPixelHeight(int $width): ?int {
    $height = $this->preferredHeight();
    return $height === null ? null : $height * (\SPTK\App::fontOrNull()?->cellHeight() ?? 16);
  }

}
