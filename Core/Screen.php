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

  public function measureArea(\SPTK\Layout\Tile $grid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $this->layout->measureArea($grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer): void {
    $this->layout->drawBackgrounds($renderer);
  }

  public function paint(\SPTK\Rendering\Grid $grid): void {
    $this->layout->paint($grid);
  }

}
