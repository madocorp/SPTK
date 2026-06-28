<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Scrollbar;

class TableContent extends Element {

  protected int $lineHeight = 0;
  protected int $tableContentWidth = 0;
  protected int $rowCount = 0;

  public function setTableGeometry(int $lineHeight, int $contentWidth, int $rowCount): void {
    $this->lineHeight = $lineHeight;
    $this->tableContentWidth = $contentWidth;
    $this->rowCount = $rowCount;
    $this->clampScroll();
  }

  public function setScroll(int $scrollX, int $scrollY): void {
    $this->scrollX = $scrollX;
    $this->scrollY = $scrollY;
    $this->clampScroll();
  }

  public function setScrollX(int $scrollX): void {
    $this->scrollX = $scrollX;
    $this->clampScroll();
  }

  public function setScrollY(int $scrollY): void {
    $this->scrollY = $scrollY;
    $this->clampScroll();
  }

  public function getScrollX(): int {
    return $this->scrollX;
  }

  public function getScrollY(): int {
    return $this->scrollY;
  }

  public function clampScroll(): void {
    $maxY = max(0, $this->rowCount * $this->lineHeight - $this->geometry->innerHeight);
    $maxX = max(0, $this->tableContentWidth - $this->geometry->innerWidth);
    $this->scrollY = max(0, min($this->scrollY, $maxY));
    $this->scrollX = max(0, min($this->scrollX, $maxX));
  }

  public function hasScrollbarOverlap(): bool {
    return $this->geometry->contentHeight > $this->geometry->height ||
      $this->geometry->contentWidth > $this->geometry->width;
  }

  public function redrawScrollbar(): void {
    if ($this->texture !== false && $this->style->get('scrollable')) {
      new Scrollbar($this->texture, $this->scrollX, $this->scrollY, $this->geometry->contentWidth, $this->geometry->contentHeight, $this->geometry, $this->style);
    }
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->x = $this->ancestor->geometry->borderLeft + $this->ancestor->geometry->paddingLeft;
    $this->geometry->y = $this->ancestor->geometry->borderTop + $this->ancestor->geometry->paddingTop + $this->lineHeight;
    $this->geometry->width = $this->ancestor->geometry->innerWidth;
    $this->geometry->height = max(0, $this->ancestor->geometry->innerHeight - $this->lineHeight);
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
    $this->geometry->contentWidth = $this->tableContentWidth;
    $this->geometry->contentHeight = $this->rowCount * $this->lineHeight;
    $this->clampScroll();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateHeights(): void {
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $this->geometry->contentHeight = $this->rowCount * $this->lineHeight;
    $this->geometry->ascent = $this->geometry->fullHeight - $this->geometry->marginBottom;
    $this->geometry->descent = $this->geometry->marginBottom;
  }

  protected function layout(): void {
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
    $this->geometry->contentWidth = $this->tableContentWidth;
    $this->geometry->contentHeight = $this->rowCount * $this->lineHeight;
    $this->clampScroll();
  }

}
