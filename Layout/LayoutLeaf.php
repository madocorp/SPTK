<?php

namespace SPTK\Layout;

use SPTK\Core\Widget;
use SPTK\Rendering\{Grid, GridWriter};

final class LayoutLeaf {

  private $grid;
  private ?Tile $area = null;

  public function __construct(public string $widget, private string $width, private string $height, private Widget $instance) {
  }

  public function setGrid($grid) {
    $this->grid = $grid;
  }

  public function grid(): Tile {
    return $this->grid;
  }

  public function paint(Grid $grid): void {
    $this->instance->paint(new GridWriter($grid, $this->grid));
  }

  public function measureArea(Tile $windowGrid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $left = 0;
    if ($this->grid->x !== 0) {
      $left = $this->grid->x * $cellWidth + $offsetX - $cellWidth;
    }
    $top = 0;
    if ($this->grid->y !== 0) {
      $top = $this->grid->y * $cellHeight + $offsetY - intdiv($cellHeight, 2);
    }
    $right = $windowWidth;
    if ($this->grid->x + $this->grid->width < $windowGrid->width) {
      $right = ($this->grid->x + $this->grid->width) * $cellWidth + $offsetX + $cellWidth;
    }
    $bottom = $windowHeight;
    if ($this->grid->y + $this->grid->height < $windowGrid->height) {
      $bottom = ($this->grid->y + $this->grid->height) * $cellHeight + $offsetY + intdiv($cellHeight + 1, 2);
    }
    $this->area = new Tile($left, $top, $right - $left, $bottom - $top);
  }

  public function drawBackground(\SPTK\Rendering\PixelRenderer $renderer): void {
    if ($this->area === null) {
      throw new \LogicException('Leaf area has not been measured.');
    }
    $renderer->fill($this->area, $this->instance->background());
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
