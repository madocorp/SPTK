<?php

namespace SPTK\Core;

/** A tile's content: measure it, then paint within the space allocated to it. */
interface Widget {

  public function measure(Size $available): Size;

  public function paint(GridWriter $writer): void;

}
