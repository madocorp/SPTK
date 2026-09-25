<?php

namespace SPTK\Widgets\Separator;

use SPTK\Core\{Color, Widget};
use SPTK\Rendering\GridWriter;

/** Separator line between other widgets */
final class Separator implements Widget {

  public function __construct() {
  }

  public function background(): Color {
    return new Color(24, 28, 36);
  }

  public function paint(GridWriter $writer): void {
  }

}
