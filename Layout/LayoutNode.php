<?php

namespace SPTK\Layout;

final class LayoutNode {

  private $children = [];
  private $grid;

  public function __construct(private string $direction, private string $width, private string $height) {
  }

  public function setGrid($grid) {
    $this->grid = $grid;
  }

  public function name() {
    return $this->direction;
  }

  public function width() {
    return $this->width;
  }

  public function height() {
    return $this->height;
  }

  public function addNode(LayoutNode $node): void {
    $this->children[] = $node;
  }

  public function addLeaf(LayoutLeaf $leaf): void {
    $this->children[] = $leaf;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    if ($this->direction === 'horizontal') {
      $widths = [];
      foreach ($this->children as $child) {
        $widths[] = $child->width();
      }
      $grids = Splitter::horizontal($grid, $widths);
    } else {
      $heights = [];
      foreach ($this->children as $child) {
        $heights[] = $child->height();
      }
      $grids = Splitter::vertical($grid, $heights);
    }
    foreach ($this->children as $i => $child) {
      $child->setGrid($grids[$i]);
      if ($child instanceof self) {
        $child->measureGrid($grids[$i]);
      }
    }
  }

  public function paint(\SPTK\Rendering\Grid $grid): void {
    foreach ($this->children as $child) {
      $child->paint($grid);
    }
  }

  public function measureArea(\SPTK\Layout\Tile $grid, array $paddings) {
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->direction}\n";
    foreach ($this->children as $child) {
      $child->debug($level + 1);
    }
  }

}
