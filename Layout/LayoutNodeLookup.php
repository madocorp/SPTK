<?php

namespace SPTK\Layout;

/** Finds XML-named layouts within a measured layout tree. */
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

}
