<?php

namespace SPTK\Core;

use SPTK\Layout\{LayoutLeaf, LayoutNode};

/** Tracks focus across the screen and enterable nested layout scopes. */
final class FocusNavigation {

  private WidgetSelection $selection;
  private array $stack = [];
  private array $remembered = [];

  /** Start with the screen's outer navigation targets. */
  public function __construct(private LayoutNode $layout) {
    $this->selection = new WidgetSelection($layout->leaves(true), $layout->movementLeaves());
  }

  /** Return the selected tile in the current navigation scope. */
  public function selectedLeaf(): ?LayoutLeaf {
    return $this->selection->selectedLeaf();
  }

  /** Return the outer selection for layout replacement. */
  public function rootSelectedLeaf(): ?LayoutLeaf {
    return $this->stack === [] ? $this->selectedLeaf() : $this->stack[0][0]->selectedLeaf();
  }

  /** Return the number of entered layout scopes. */
  public function depth(): int {
    return count($this->stack);
  }

  /** Report whether a tile belongs to this or an ancestor navigation scope. */
  public function contains(LayoutLeaf $leaf): bool {
    $known = $this->selection->contains($leaf);
    foreach ($this->stack as [$parent]) {
      $known = $known || $parent->contains($leaf);
    }
    return $known;
  }

  /** Select a tile in this or an ancestor scope. */
  public function select(LayoutLeaf $leaf): bool {
    if (!$this->contains($leaf)) {
      throw new \InvalidArgumentException('Selected leaf does not belong to this navigation scope.');
    }
    $previous = $this->selectedLeaf();
    while (!$this->selection->contains($leaf) && $this->stack !== []) {
      $this->leave();
    }
    $this->selection->select($leaf);
    return $previous !== $this->selectedLeaf();
  }

  /** Move to the next eligible tile inside the current scope. */
  public function move(string $direction): bool {
    return $this->selection->move($direction);
  }

  /** Enter the selected layout and restore its last child selection when available. */
  public function enter(): bool {
    $node = $this->layout->enterableFor($this->selectedLeaf());
    if ($node === null || $node->childNavigationLeaves() === []) {
      return false;
    }
    $id = spl_object_id($node);
    $children = $this->remembered[$id] ?? null;
    if ($children === null) {
      $targets = $node->childMovementLeaves();
      $children = new WidgetSelection($node->childNavigationLeaves(), $targets);
      if ($targets !== []) {
        $children->select($targets[0]);
      }
    }
    $this->stack[] = [$this->selection, $node];
    $this->selection = $children;
    return true;
  }

  /** Leave the current layout and return to its outer focus tile. */
  public function leave(): bool {
    if ($this->stack === []) {
      return false;
    }
    [$parent, $node] = array_pop($this->stack);
    $this->remembered[spl_object_id($node)] = $this->selection;
    $this->selection = $parent;
    return true;
  }

}
