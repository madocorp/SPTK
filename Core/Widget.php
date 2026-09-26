<?php

namespace SPTK\Core;

use SPTK\Rendering\GridWriter;

/** A tile's content: measure it, then paint within the space allocated to it. */
interface Widget {

  /** Subscribe a named handler to an event emitted by this widget. */
  public function on(string $event, callable $listener): void;

  /** Emit an event to this widget's registered handlers. */
  public function emit(string $event): void;

  /** Return the color used to fill the widget's tile. */
  public function background(): Color;

  /** Paint the widget into its allocated tile. */
  public function paint(GridWriter $writer): void;

}
