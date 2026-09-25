<?php

namespace SPTK\Layout;

use SPTK\Core\Widget;
use SPTK\Rendering\{Grid, GridWriter};

final class LayoutLeaf {

  private $grid;

  public function __construct(public string $widget, private string $width, private string $height, private Widget $instance) {
  }

  public function setGrid($grid) {
    $this->grid = $grid;
  }

  public function paint(Grid $grid): void {
    $this->instance->paint(new GridWriter($grid, $this->grid));
  }

  public function name() {
    return $this->widget;
  }

  public function width() {
    return $this->width;
  }

  public function height() {
    return $this->height;
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->widget}\n";
  }

}
