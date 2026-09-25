<?php

namespace SPTK\Core;

final class Screen {

  public function __construct(public \SPTK\Layout\LayoutNode $layout) {

  }

  public function handleEvent(mixed $event): bool {
    return true;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->layout->measureGrid($grid);
  }

  public function measureArea(\SPTK\Layout\Tile $grid, array $paddings) {
    $this->layout->measureArea($grid, $paddings);
  }

  public function paint() {

  }

}
