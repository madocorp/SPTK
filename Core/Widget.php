<?php

namespace SPTK\Core;

use SPTK\Rendering\GridWriter;
use SPTK\Rendering\PixelRenderer;
use SPTK\Layout\Tile;

/** Base behavior shared by widgets that measure themselves and paint into a tile. */
abstract class Widget {

  /** Subscribe a named handler to an event emitted by this widget. */
  abstract public function on(string $event, callable $listener): void;

  /** Emit an event to this widget's registered handlers. */
  abstract public function emit(string $event): void;

  /** Handle a raw input event while this widget is activated. */
  abstract public function handleInput(mixed $event): bool;

  /** Return the color used to fill the widget's tile. */
  abstract public function background(): Color;

  /** Paint the widget into its allocated tile. */
  abstract public function paint(GridWriter $writer): void;

  /** Paint optional pixel content over the completed character grid. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
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

}
