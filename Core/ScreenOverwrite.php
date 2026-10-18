<?php

namespace SPTK\Core;

use SPTK\Layout\{LayoutLeaf, LayoutNode};

/** Shows and restores a screen's hidden overwrite elements and returns focus afterwards. */
final class ScreenOverwrite {

  private array $focusBefore = [];

  public function __construct(private Screen $screen) {
  }

  /** Show a hidden element in place of its target, optionally focusing it; false when already shown. */
  public function show(string $id, bool $focus = true): bool {
    $tile = $this->overwriter($id);
    if ($tile->overwriting() !== null) {
      return false;
    }
    $target = $this->target($tile);
    if ($target->overwrittenBy() !== null) {
      throw new \LogicException("Overwrite target of #{$id} is already overwritten.");
    }
    if (!$focus) {
      $tile->setOverwriting($target);
      $this->screen->setLayout($this->screen->layout);
      return true;
    }
    $this->focusBefore[spl_object_id($tile)] = $this->screen->selectedLeaf();
    $this->screen->release();
    $tile->setOverwriting($target);
    $this->screen->setLayout($this->screen->layout);
    if ($tile instanceof LayoutLeaf) {
      if ($this->selectable($tile)) {
        $this->screen->activateLeaf($tile);
      }
    } else {
      $first = $tile->movementLeaves()[0] ?? $tile->leaves(true)[0] ?? null;
      if ($first !== null && $this->selectable($first)) {
        $this->screen->selectLeaf($first);
      }
    }
    return true;
  }

  /** Put the target of a shown element back; false when it is not shown. */
  public function restore(string $id): bool {
    return $this->restoreTile($this->overwriter($id));
  }

  /** Restore the nearest shown element around a leaf, used by `restores="parent"`. */
  public function restoreAround(LayoutLeaf $leaf): bool {
    $path = $this->screen->layout->pathTo($leaf) ?? [];
    foreach ([$leaf, ...array_reverse($path)] as $tile) {
      if ($tile->isOverwriter() && $tile->overwriting() !== null) {
        return $this->restoreTile($tile);
      }
    }
    return false;
  }

  /** Release a widget inside the element, put its target back, and focus the earlier leaf. */
  public function restoreTile(LayoutNode|LayoutLeaf $tile): bool {
    if ($tile->overwriting() === null) {
      return false;
    }
    $active = $this->screen->activeLeaf();
    if ($active !== null && ($active === $tile || ($tile instanceof LayoutNode && $tile->containsLeaf($active)))) {
      $this->screen->release();
      if ($tile->overwriting() === null) {
        return true;
      }
    }
    $tile->setOverwriting(null);
    $this->screen->setLayout($this->screen->layout);
    $focus = $this->focusBefore[spl_object_id($tile)] ?? null;
    unset($this->focusBefore[spl_object_id($tile)]);
    if ($focus !== null && $this->selectable($focus)) {
      $this->screen->selectLeaf($focus);
    }
    return true;
  }

  /** Find a hidden overwrite element by its layout or widget id. */
  private function overwriter(string $id): LayoutNode|LayoutLeaf {
    $tile = $this->screen->layout->findTile($id);
    if ($tile === null || !$tile->isOverwriter()) {
      throw new \InvalidArgumentException("No overwrite element with id: {$id}");
    }
    return $tile;
  }

  /** Resolve `parent` or `#id` to the tile that the element replaces. */
  private function target(LayoutNode|LayoutLeaf $tile): LayoutNode|LayoutLeaf {
    $root = $this->screen->layout;
    $path = $root->pathTo($tile);
    $target = $tile->overwriteTarget() === 'parent' ? end($path) : $root->findTile(substr($tile->overwriteTarget(), 1));
    if ($target === null || $target === false) {
      throw new \RuntimeException('Overwrite target not found: ' . $tile->overwriteTarget());
    }
    if ($target === $root || $target === $tile) {
      throw new \LogicException('Overwrite cannot replace the screen root layout or itself.');
    }
    return $target;
  }

  /** Report whether a leaf can be selected from the screen's outer navigation scope. */
  private function selectable(LayoutLeaf $leaf): bool {
    return in_array($leaf, $this->screen->layout->leaves(true), true);
  }

}
