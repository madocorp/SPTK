<?php

namespace SPTK\Layout;

/** Finds XML-named layouts and widgets within a layout tree, including hidden ones. */
trait LayoutNodeLookup {

  /** Find a named layout in this subtree for an in-place layout swap. */
  public function findNode(string $id): ?LayoutNode {
    if ($this->id === $id) {
      return $this;
    }
    foreach ($this->children as $child) {
      if ($child instanceof LayoutNode && ($found = $child->findNode($id)) !== null) {
        return $found;
      }
    }
    return null;
  }

  /** Find a layout by its id or a leaf by its widget id. */
  public function findTile(string $id): LayoutNode|LayoutLeaf|null {
    if ($this->id === $id) {
      return $this;
    }
    foreach ($this->children as $child) {
      if ($child instanceof LayoutLeaf && $child->instance()->id() === $id) {
        return $child;
      }
      if ($child instanceof LayoutNode && ($found = $child->findTile($id)) !== null) {
        return $found;
      }
    }
    return null;
  }

  /** Return the layouts from this one down to the parent of a tile, or null when it is not inside. */
  public function pathTo(LayoutNode|LayoutLeaf $tile): ?array {
    foreach ($this->children as $child) {
      if ($child === $tile) {
        return [$this];
      }
      if ($child instanceof LayoutNode && ($path = $child->pathTo($tile)) !== null) {
        return [$this, ...$path];
      }
    }
    return null;
  }

  /** Return every leaf, hidden ones and group focus leaves too, for widget lookup. */
  public function allLeaves(): array {
    $leaves = $this->focus === null ? [] : [$this->focus];
    foreach ($this->children as $child) {
      if ($child instanceof LayoutNode) {
        array_push($leaves, ...$child->allLeaves());
      } else if ($child instanceof LayoutLeaf) {
        $leaves[] = $child;
      }
    }
    return $leaves;
  }

}
