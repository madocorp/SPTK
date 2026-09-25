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

  public function grid(): Tile {
    return $this->grid;
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

  public function addSeparator(LayoutSeparator $separator): void {
    $this->children[] = $separator;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->grid = $grid;
    $content = [];
    foreach ($this->children as $index => $child) {
      if (!$child instanceof LayoutSeparator) {
        $content[$index] = $child;
      } else if ($index === 0 || $index === count($this->children) - 1 || $this->children[$index - 1] instanceof LayoutSeparator || $this->children[$index + 1] instanceof LayoutSeparator) {
        throw new \RuntimeException('Separator must be placed between two layout items.');
      }
    }
    if ($this->direction === 'horizontal') {
      $widths = [];
      foreach ($content as $child) {
        $widths[] = $child->width();
      }
      $grids = Splitter::horizontal($grid, $widths);
    } else {
      $heights = [];
      foreach ($content as $child) {
        $heights[] = $child->height();
      }
      $grids = Splitter::vertical($grid, $heights);
    }
    $gridIndex = 0;
    foreach ($content as $child) {
      $child->setGrid($grids[$gridIndex]);
      if ($child instanceof self) {
        $child->measureGrid($grids[$gridIndex]);
      }
      $gridIndex++;
    }
  }

  public function paint(\SPTK\Rendering\Grid $grid): void {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $child->paint($grid);
    }
  }

  public function measureArea(\SPTK\Layout\Tile $grid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      if ($child instanceof self) {
        $child->measureArea($grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
      } else {
        $child->measureArea($grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
      }
    }
    foreach ($this->children as $index => $child) {
      if ($child instanceof LayoutSeparator) {
        $this->measureSeparatorArea($index, $grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
      }
    }
  }

  private function measureSeparatorArea(int $index, \SPTK\Layout\Tile $windowGrid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $separator = $this->children[$index];
    $before = $this->children[$index - 1];
    $beforeGrid = $before->grid();
    $left = 0;
    if ($this->grid->x !== 0) {
      $left = $this->grid->x * $cellWidth + $offsetX - $cellWidth;
    }
    $right = $windowWidth;
    if ($this->grid->x + $this->grid->width < $windowGrid->width) {
      $right = ($this->grid->x + $this->grid->width) * $cellWidth + $offsetX + $cellWidth;
    }
    $top = 0;
    if ($this->grid->y !== 0) {
      $top = $this->grid->y * $cellHeight + $offsetY - intdiv($cellHeight, 2);
    }
    $bottom = $windowHeight;
    if ($this->grid->y + $this->grid->height < $windowGrid->height) {
      $bottom = ($this->grid->y + $this->grid->height) * $cellHeight + $offsetY + intdiv($cellHeight + 1, 2);
    }
    if ($this->direction === 'horizontal') {
      $boundary = ($beforeGrid->x + $beforeGrid->width) * $cellWidth + $offsetX + $cellWidth;
      $separator->setArea(new Tile($boundary - 1, $top, 2, $bottom - $top));
    } else {
      $boundary = ($beforeGrid->y + $beforeGrid->height) * $cellHeight + $offsetY + intdiv($cellHeight + 1, 2);
      $separator->setArea(new Tile($left, $boundary - 1, $right - $left, 2));
    }
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer): void {
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $child->drawBackgrounds($renderer);
      } else if (!$child instanceof LayoutSeparator) {
        $child->drawBackground($renderer);
      }
    }
  }

  public function drawSeparators(\SPTK\Rendering\PixelRenderer $renderer, \SPTK\Core\Color $color): void {
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $child->drawSeparators($renderer, $color);
      } else if ($child instanceof LayoutSeparator) {
        $child->draw($renderer, $color);
      }
    }
  }

  public function debug(int $level = 0) {
    $pad = str_repeat('  ', $level);
    echo "{$pad}{$this->direction}\n";
    foreach ($this->children as $child) {
      $child->debug($level + 1);
    }
  }

}
