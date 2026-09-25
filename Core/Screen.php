<?php

namespace SPTK\Core;

final class Screen {

  private array $leaves;
  private int $selectedIndex = 0;
  private bool $inputMode = false;

  public function __construct(public \SPTK\Layout\LayoutNode $layout, public Color $borderColor = new Color(85, 85, 85)) {
    $this->leaves = $layout->leaves();
  }

  /** Handle widget navigation, activation, and active-widget input. */
  public function handleEvent(mixed $event): bool {
    if ($this->inputMode) {
      if ($this->selectedLeaf() === null) {
        $this->inputMode = false;
        return false;
      }
      if ($event->type === \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN) {
        $key = $event->key->key;
        if ($key === \SPTK\SDLWrapper\SDL::KEY_RETURN || $key === \SPTK\SDLWrapper\SDL::KEY_ESCAPE) {
          $this->inputMode = false;
          return true;
        }
      }
      return $this->leaves[$this->selectedIndex]->handleEvent($event);
    }
    if ($event->type !== \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $key = $event->key->key;
    if ($key === \SPTK\SDLWrapper\SDL::KEY_RETURN) {
      $this->inputMode = true;
      return true;
    }
    $direction = match ($key) {
      \SPTK\SDLWrapper\SDL::KEY_UP => 'up',
      \SPTK\SDLWrapper\SDL::KEY_DOWN => 'down',
      \SPTK\SDLWrapper\SDL::KEY_LEFT => 'left',
      \SPTK\SDLWrapper\SDL::KEY_RIGHT => 'right',
      default => null,
    };
    if ($direction !== null) {
      $this->moveSelection($direction);
      return true;
    }
    return true;
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->layout->measureGrid($grid);
  }

  public function measureArea(\SPTK\Layout\Tile $grid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $this->layout->measureArea($grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer): void {
    $this->layout->drawBackgrounds($renderer, $this->selectedLeaf());
  }

  public function drawSeparators(\SPTK\Rendering\PixelRenderer $renderer): void {
    $this->layout->drawSeparators($renderer, $this->borderColor);
  }

  public function paint(\SPTK\Rendering\Grid $grid): void {
    $this->layout->paint($grid, $this->selectedLeaf());
  }

  /** Return the currently selected widget leaf. */
  private function selectedLeaf(): ?\SPTK\Layout\LayoutLeaf {
    return $this->leaves[$this->selectedIndex] ?? null;
  }

  /** Move focus to the nearest widget in the requested direction. */
  private function moveSelection(string $direction): void {
    $current = $this->selectedLeaf();
    if ($current === null) {
      return;
    }
    $tile = $current->grid();
    $allCandidates = [];
    foreach ($this->leaves as $index => $leaf) {
      if ($index === $this->selectedIndex) {
        continue;
      }
      $next = $leaf->grid();
      if (!$this->isInDirection($tile, $next, $direction)) {
        continue;
      }
      $candidate = ['index' => $index, 'tile' => $next];
      $allCandidates[] = $candidate;
    }
    if ($allCandidates === []) {
      return;
    }
    $nearestGap = INF;
    foreach ($allCandidates as $candidate) {
      $nearestGap = min($nearestGap, $this->directionalGap($tile, $candidate['tile'], $direction));
    }
    $nearestRow = [];
    $alignedRow = [];
    foreach ($allCandidates as $candidate) {
      if ($this->directionalGap($tile, $candidate['tile'], $direction) !== $nearestGap) {
        continue;
      }
      $nearestRow[] = $candidate;
      if ($this->overlapsOnCrossAxis($tile, $candidate['tile'], $direction)) {
        $alignedRow[] = $candidate;
      }
    }
    $best = $alignedRow !== []
      ? $this->nearestRowOrColumn($tile, $alignedRow, $direction, false)
      : $this->nearestRowOrColumn($tile, $allCandidates, $direction, true);
    if ($best !== null) {
      $this->selectedIndex = $best;
    }
  }

  /** Check whether a candidate lies forward of the current tile. */
  private function isInDirection(\SPTK\Layout\Tile $current, \SPTK\Layout\Tile $candidate, string $direction): bool {
    return match ($direction) {
      'left' => ($current->x + $current->width > $candidate->x + $candidate->width || $current->x >= $candidate->x + $candidate->width) && $current->x > $candidate->x,
      'right' => ($current->x < $candidate->x || $current->x + $current->width <= $candidate->x) && $current->x + $current->width < $candidate->x + $candidate->width,
      'up' => ($current->y + $current->height > $candidate->y + $candidate->height || $current->y >= $candidate->y + $candidate->height) && $current->y > $candidate->y,
      default => ($current->y < $candidate->y || $current->y + $current->height <= $candidate->y) && $current->y + $current->height < $candidate->y + $candidate->height,
    };
  }

  /** Check whether two tiles overlap along the axis perpendicular to movement. */
  private function overlapsOnCrossAxis(\SPTK\Layout\Tile $current, \SPTK\Layout\Tile $candidate, string $direction): bool {
    if ($direction === 'left' || $direction === 'right') {
      return $candidate->y < $current->y + $current->height && $candidate->y + $candidate->height > $current->y;
    }
    return $candidate->x < $current->x + $current->width && $candidate->x + $candidate->width > $current->x;
  }

  /** Choose from the nearest aligned row or column, or the nearest fallback tile. */
  private function nearestRowOrColumn(\SPTK\Layout\Tile $current, array $candidates, string $direction, bool $useGeometry): ?int {
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
  private function directionalGap(\SPTK\Layout\Tile $current, \SPTK\Layout\Tile $candidate, string $direction): int {
    return match ($direction) {
      'left' => max(0, $current->x - ($candidate->x + $candidate->width)),
      'right' => max(0, $candidate->x - ($current->x + $current->width)),
      'up' => max(0, $current->y - ($candidate->y + $candidate->height)),
      default => max(0, $candidate->y - ($current->y + $current->height)),
    };
  }

  /** Measure distance between tile ranges perpendicular to movement. */
  private function crossAxisDistance(\SPTK\Layout\Tile $current, \SPTK\Layout\Tile $candidate, string $direction): int {
    if ($direction === 'left' || $direction === 'right') {
      return max(0, $current->y - ($candidate->y + $candidate->height), $candidate->y - ($current->y + $current->height));
    }
    return max(0, $current->x - ($candidate->x + $candidate->width), $candidate->x - ($current->x + $current->width));
  }

  /** Measure distance between the matching left or top tile edges. */
  private function edgeAlignmentDistance(\SPTK\Layout\Tile $current, \SPTK\Layout\Tile $candidate, string $direction): int {
    return $direction === 'left' || $direction === 'right'
      ? abs($current->y - $candidate->y)
      : abs($current->x - $candidate->x);
  }

}
