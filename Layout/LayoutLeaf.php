<?php

namespace SPTK\Layout;

final class LayoutLeaf {

  private $grid;

  public function __construct(public string $widget, private string $width, private string $height) {
  }

  public function setGrid($grid) {
    $this->grid = $grid;
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
