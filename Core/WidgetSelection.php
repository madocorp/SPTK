<?php

namespace SPTK\Core;

use SPTK\Layout\{LayoutLeaf, Tile};

/** Tracks the selected widget and moves focus through the tile geometry. */
final class WidgetSelection {

  private int $selectedIndex = 0;

  /** Create a selection model with optional arrow-movement destinations. */
  public function __construct(private array $leaves, private ?array $movementLeaves = null) {
  }

  /** Return the currently selected widget leaf. */
  public function selectedLeaf(): ?LayoutLeaf {
    return $this->leaves[$this->selectedIndex] ?? null;
  }

  /** Report whether this selection scope contains a leaf. */
  public function contains(LayoutLeaf $leaf): bool {
    return in_array($leaf, $this->leaves, true);
  }

  /** Select a known leaf and report whether focus changed. */
  public function select(LayoutLeaf $leaf): bool {
    foreach ($this->leaves as $index => $candidate) {
      if ($candidate === $leaf) {
        if ($index === $this->selectedIndex) {
          return false;
        }
        $this->selectedIndex = $index;
        return true;
      }
    }
    throw new \InvalidArgumentException('Selected leaf does not belong to this screen.');
  }

  /** Move focus in the requested direction and report whether the selection changed. */
  public function move(string $direction): bool {
    $current = $this->selectedLeaf();
    if ($current === null) {
      return false;
    }
    $tile = $current->navigationArea();
    $allCandidates = [];
    foreach ($this->leaves as $index => $leaf) {
      if ($index === $this->selectedIndex || !in_array($leaf, $this->movementLeaves ?? $this->leaves, true)) {
        continue;
      }
      $next = $leaf->navigationArea();
      if (!$this->isInDirection($tile, $next, $direction)) {
        continue;
      }
      $allCandidates[] = ['index' => $index, 'tile' => $next];
    }
    if ($allCandidates === []) {
      return false;
    }
    $alignedCandidates = [];
    foreach ($allCandidates as $candidate) {
      if ($this->overlapsOnCrossAxis($tile, $candidate['tile'], $direction)) {
        $alignedCandidates[] = $candidate;
      }
    }
    $best = $alignedCandidates !== []
      ? $this->nearestRowOrColumn($tile, $alignedCandidates, $direction, false)
      : $this->nearestRowOrColumn($tile, $allCandidates, $direction, true);
    if ($best === null || $best === $this->selectedIndex) {
      return false;
    }
    $this->selectedIndex = $best;
    return true;
  }

  /** Check whether a candidate lies forward of the current tile. */
  private function isInDirection(Tile $current, Tile $candidate, string $direction): bool {
    return match ($direction) {
      'left' => ($current->x + $current->width > $candidate->x + $candidate->width || $current->x >= $candidate->x + $candidate->width) && $current->x > $candidate->x,
      'right' => ($current->x < $candidate->x || $current->x + $current->width <= $candidate->x) && $current->x + $current->width < $candidate->x + $candidate->width,
      'up' => ($current->y + $current->height > $candidate->y + $candidate->height || $current->y >= $candidate->y + $candidate->height) && $current->y > $candidate->y,
      default => ($current->y < $candidate->y || $current->y + $current->height <= $candidate->y) && $current->y + $current->height < $candidate->y + $candidate->height,
    };
  }

  /** Check whether two tiles overlap along the axis perpendicular to movement. */
  private function overlapsOnCrossAxis(Tile $current, Tile $candidate, string $direction): bool {
    if ($direction === 'left' || $direction === 'right') {
      return $candidate->y < $current->y + $current->height && $candidate->y + $candidate->height > $current->y;
    }
    return $candidate->x < $current->x + $current->width && $candidate->x + $candidate->width > $current->x;
  }

  /** Choose from the nearest aligned row or column, or the nearest fallback tile. */
  private function nearestRowOrColumn(Tile $current, array $candidates, string $direction, bool $useGeometry): ?int {
    if ($candidates === []) {
      return null;
    }
    $bestIndex = null;
    $bestPrimary = INF;
    $bestSecondary = INF;
    foreach ($candidates as $candidate) {
      $tile = $candidate['tile'];
      $primary = $this->directionalGap($current, $tile, $direction);
      $secondary = $this->edgeAlignmentDistance($current, $tile, $direction);
      if ($useGeometry) {
        $crossDistance = $this->crossAxisDistance($current, $tile, $direction);
        $primary = $primary ** 2 + $crossDistance ** 2;
      }
      if ($primary < $bestPrimary || ($primary === $bestPrimary && $secondary < $bestSecondary)) {
        $bestPrimary = $primary;
        $bestSecondary = $secondary;
        $bestIndex = $candidate['index'];
      }
    }
    return $bestIndex;
  }

  /** Measure edge-to-edge distance along the requested direction. */
  private function directionalGap(Tile $current, Tile $candidate, string $direction): int {
    return match ($direction) {
      'left' => max(0, $current->x - ($candidate->x + $candidate->width)),
      'right' => max(0, $candidate->x - ($current->x + $current->width)),
      'up' => max(0, $current->y - ($candidate->y + $candidate->height)),
      default => max(0, $candidate->y - ($current->y + $current->height)),
    };
  }

  /** Measure distance between tile ranges perpendicular to movement. */
  private function crossAxisDistance(Tile $current, Tile $candidate, string $direction): int {
    if ($direction === 'left' || $direction === 'right') {
      return max(0, $current->y - ($candidate->y + $candidate->height), $candidate->y - ($current->y + $current->height));
    }
    return max(0, $current->x - ($candidate->x + $candidate->width), $candidate->x - ($current->x + $current->width));
  }

  /** Measure distance between the matching left or top tile edges. */
  private function edgeAlignmentDistance(Tile $current, Tile $candidate, string $direction): int {
    return $direction === 'left' || $direction === 'right'
      ? abs($current->y - $candidate->y)
      : abs($current->x - $candidate->x);
  }

}
