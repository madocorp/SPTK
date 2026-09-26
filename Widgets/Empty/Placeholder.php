<?php

namespace SPTK\Widgets\Empty;

use SPTK\Core\{Color, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\GridWriter;

/** A blank placeholder widget that reserves a tile without drawing content. */
final class Placeholder extends Widget {

  use WidgetEventEmitter;

  public function __construct(private readonly Color $bg) {
  }

  /** Paint blank cells with the tile background. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->bg, $this->bg);
  }

  /** Return the color used to fill the tile behind its content. */
  public function background(): Color {
    return $this->bg;
  }

  /** Ignore raw input while this placeholder is activated. */
  public function handleInput(mixed $event): bool {
    return false;
  }

}
