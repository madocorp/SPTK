<?php

namespace SPTK\Core;

/** A tile's content: measure it, then paint within the space allocated to it. */
interface Widget {

  public function paint(\SPTK\Rendering\GridWriter $writer): void;

}
