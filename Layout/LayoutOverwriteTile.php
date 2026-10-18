<?php

namespace SPTK\Layout;

/** Lets a hidden layout or leaf take the place and the size of another tile while shown. */
trait LayoutOverwriteTile {

  private ?string $overwriteTarget = null;
  private LayoutNode|LayoutLeaf|null $overwriting = null;
  private LayoutNode|LayoutLeaf|null $overwrittenBy = null;

  /** Hide this tile until shown in place of `parent` or `#id`. */
  public function setOverwrite(string $target): void {
    if ($target !== 'parent' && !preg_match('/^#[A-Za-z_][A-Za-z0-9_-]*$/', $target)) {
      throw new \InvalidArgumentException('Overwrite target must be "parent" or "#id".');
    }
    $this->overwriteTarget = $target;
  }

  public function overwriteTarget(): ?string {
    return $this->overwriteTarget;
  }

  public function isOverwriter(): bool {
    return $this->overwriteTarget !== null;
  }

  /** Return the tile this one replaces while it is shown. */
  public function overwriting(): LayoutNode|LayoutLeaf|null {
    return $this->overwriting;
  }

  /** Return the shown tile that replaces this one. */
  public function overwrittenBy(): LayoutNode|LayoutLeaf|null {
    return $this->overwrittenBy;
  }

  /** Show this tile in place of a target, or hide it again with null. */
  public function setOverwriting(LayoutNode|LayoutLeaf|null $target): void {
    $this->overwriting?->setOverwrittenBy(null);
    $this->overwriting = $target;
    $target?->setOverwrittenBy($this);
  }

  /** Called by the overwriting tile; use setOverwriting() instead. */
  public function setOverwrittenBy(LayoutNode|LayoutLeaf|null $tile): void {
    $this->overwrittenBy = $tile;
  }

  /** Follow active overwrites from a tile to the one that is really shown. */
  private static function shownTile(LayoutNode|LayoutLeaf $tile): LayoutNode|LayoutLeaf {
    while ($tile->overwrittenBy() !== null) {
      $tile = $tile->overwrittenBy();
    }
    return $tile;
  }

  /** While shown, use the target's size instead of this tile's own size. */
  private function overwriteAdjustedSize(string $size, string $axis): string {
    if ($this->overwriting === null) {
      return $size;
    }
    return $axis === 'width' ? $this->overwriting->width() : $this->overwriting->height();
  }

}
