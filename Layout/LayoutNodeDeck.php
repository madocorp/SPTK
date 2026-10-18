<?php

namespace SPTK\Layout;

/** Keeps one open tile among the deck children of a layout. */
trait LayoutNodeDeck {

  /** Open the deck tile around a leaf, at every nesting level; true when a size changed. */
  public function openDeckFor(LayoutLeaf $leaf): bool {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator || $child->isOverwriter()) {
        continue;
      }
      $shown = self::shownTile($child);
      if ($shown === $leaf) {
        return $this->openDeckChild($child);
      }
      if ($shown instanceof LayoutNode && $shown->containsLeaf($leaf)) {
        $changed = $this->openDeckChild($child);
        return $shown->openDeckFor($leaf) || $changed;
      }
    }
    return false;
  }

  /** Open the deck tile with this layout or widget id and its deck ancestors; false when not found. */
  public function openDeckTile(string $id): bool {
    foreach ($this->children as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      if ($child->isDeckTile() && $child->tileId() === $id) {
        $this->openDeckChild($child);
        return true;
      }
      if ($child instanceof LayoutNode && $child->openDeckTile($id)) {
        $this->openDeckChild($child);
        return true;
      }
    }
    return false;
  }

  /** Report whether a shown leaf, including a group focus leaf, is inside this layout. */
  public function containsLeaf(LayoutLeaf $leaf): bool {
    if ($this->focus === $leaf) {
      return true;
    }
    foreach ($this->visibleChildren() as $child) {
      if ($child === $leaf || ($child instanceof LayoutNode && $child->containsLeaf($leaf))) {
        return true;
      }
    }
    return false;
  }

  /** The first deck tile starts open when no sibling is open yet. */
  private function openFirstDeckTile(LayoutNode|LayoutLeaf $child): void {
    if (!$child->isDeckTile()) {
      return;
    }
    foreach ($this->children as $sibling) {
      if ($sibling !== $child && !$sibling instanceof LayoutSeparator && $sibling->isDeckOpen()) {
        return;
      }
    }
    $child->setDeckOpen(true);
  }

  /** Close the open sibling and open this tile, sending collapse and expand notifications. */
  private function openDeckChild(LayoutNode|LayoutLeaf $child): bool {
    if (!$child->isDeckTile() || $child->isDeckOpen()) {
      return false;
    }
    foreach ($this->children as $sibling) {
      if (!$sibling instanceof LayoutSeparator && $sibling->isDeckOpen()) {
        $sibling->setDeckOpen(false);
        self::notifyDeckTile($sibling, 'collapse');
      }
    }
    $child->setDeckOpen(true);
    self::notifyDeckTile($child, 'expand');
    return true;
  }

  /** Send a deck notification to every widget in the tile. */
  private static function notifyDeckTile(LayoutNode|LayoutLeaf $tile, string $type): void {
    foreach ($tile instanceof LayoutLeaf ? [$tile] : $tile->leaves() as $leaf) {
      $leaf->dispatchNotification($type);
    }
  }

}
