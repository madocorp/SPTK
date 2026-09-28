<?php

namespace SPTK\Layout;

/** Splits a grid tile into child layouts and widgets and measures their separator areas. */
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

  public function paint(\SPTK\Rendering\Grid $grid, ?LayoutLeaf $selected = null): void {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      if ($child instanceof self) {
        $child->paint($grid, $selected);
      } else {
        $child->paint($grid, $selected === null || $child === $selected);
      }
    }
  }

  /** Measure child backgrounds and separator areas in the shared window coordinate system. */
  public function measureArea(Tile $grid, WindowGeometry $geometry): void {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $child->measureArea($grid, $geometry);
    }
    foreach ($this->children as $index => $child) {
      if ($child instanceof LayoutSeparator) {
        $this->measureSeparatorArea($index, $grid, $geometry);
      }
    }
  }

  /** Measure one separator after its preceding child within this layout's padded area. */
  private function measureSeparatorArea(int $index, Tile $windowGrid, WindowGeometry $geometry): void {
    $separator = $this->children[$index];
    $before = $this->children[$index - 1];
    $separator->setArea($geometry->separatorArea($this->grid, $before->grid(), $windowGrid, $this->direction));
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer, ?LayoutLeaf $selected = null): void {
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $child->drawBackgrounds($renderer, $selected);
      } else if (!$child instanceof LayoutSeparator) {
        $child->drawBackground($renderer, $selected === null || $child === $selected);
      }
    }
  }

  /** Return widget leaves in their XML definition order. */
  public function leaves(): array {
    $leaves = [];
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->leaves());
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
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
