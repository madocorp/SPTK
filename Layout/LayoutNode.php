<?php

namespace SPTK\Layout;

/** Splits a grid tile into child layouts and widgets and measures their separator areas. */
final class LayoutNode {

  private $children = [];
  private $grid;
  private ?LayoutLeaf $focus = null;

  public function __construct(private string $direction, private string $width, private string $height, bool $navigateChildren = true, ?string $id = null, ?string $tip = null, private bool $navigate = true, private bool $enterChildren = false) {
    if ($enterChildren && $navigateChildren) {
      throw new \InvalidArgumentException('enterChildren requires navigateChildren="false".');
    }
    if (!$navigateChildren) {
      $widget = new \SPTK\Widgets\Empty\Placeholder(new \SPTK\Core\Color(0, 0, 0));
      $widget->setId($id);
      $widget->setTips($tip ?? ($enterChildren ? 'Return enters this layout; Esc leaves it.' : null));
      $this->focus = new LayoutLeaf('Layout', $width, $height, $widget);
    }
  }

  public function setGrid($grid) {
    $this->grid = $grid;
    $this->focus?->setGrid($grid);
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

  /** Replace a child subtree or leaf while retaining the other layout items and their widgets. */
  public function replaceChild(LayoutNode|LayoutLeaf $current, LayoutNode|LayoutLeaf $replacement): bool {
    foreach ($this->children as $index => $child) {
      if ($child === $current) {
        $this->children[$index] = $replacement;
        return true;
      }
      if ($child instanceof self && $child->replaceChild($current, $replacement)) {
        return true;
      }
    }
    return false;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->grid = $grid;
    $this->focus?->setGrid($grid);
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
    if ($selected === $this->focus) {
      $selected = null;
    }
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
    if ($selected === $this->focus) {
      $selected = null;
    }
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $child->drawBackgrounds($renderer, $selected);
      } else if (!$child instanceof LayoutSeparator) {
        $child->drawBackground($renderer, $selected === null || $child === $selected);
      }
    }
  }

  /** Return rendered leaves or navigation targets, treating grouped layouts as single tiles. */
  public function leaves(bool $navigationOnly = false): array {
    if ($navigationOnly && $this->focus !== null) {
      return [$this->focus];
    }
    $leaves = [];
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->leaves($navigationOnly));
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Return the focus targets inside this grouped layout. */
  public function childNavigationLeaves(): array {
    $leaves = [];
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->leaves(true));
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Return arrow destinations inside this grouped layout. */
  public function childMovementLeaves(): array {
    $leaves = [];
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        array_push($leaves, ...$child->movementLeaves());
      } else if ($child instanceof LayoutLeaf && $child->navigate()) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

  /** Find an enterable grouped layout by its focus tile. */
  public function enterableFor(?LayoutLeaf $focus): ?self {
    if ($focus !== null && $this->focus === $focus && $this->enterChildren) {
      return $this;
    }
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $found = $child->enterableFor($focus);
        if ($found !== null) {
          return $found;
        }
      }
    }
    return null;
  }

  /** Return focus tiles eligible as destinations during arrow movement. */
  public function movementLeaves(): array {
    if (!$this->navigate) {
      return [];
    }
    if ($this->focus !== null) {
      return [$this->focus];
    }
    return $this->childMovementLeaves();
  }

  /** Resolve a focused layout to its rendered descendants for pixel selection colors. */
  public function focusedLeaves(?LayoutLeaf $selected): array {
    if ($this->focus !== null && $selected === $this->focus) {
      return $this->leaves();
    }
    foreach ($this->children as $child) {
      if ($child instanceof self) {
        $leaves = $child->focusedLeaves($selected);
        if ($leaves !== []) {
          return $leaves;
        }
      }
    }
    return [];
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
