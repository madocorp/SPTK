<?php

namespace SPTK\Core;

use SPTK\Rendering\GridWriter;

/** A tile's content: measure it, then paint within the space allocated to it. */
interface Widget {

  public function background(): Color;

  public function paint(GridWriter $writer): void;

}
