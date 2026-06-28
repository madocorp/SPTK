<?php

namespace SPTK\Elements;

use \SPTK\Element;

class TableHeader extends Element {

  protected int $lineHeight = 0;
  protected int $tableContentWidth = 0;

  public function setTableGeometry(int $lineHeight, int $contentWidth, int $scrollX): void {
    $this->lineHeight = $lineHeight;
    $this->tableContentWidth = $contentWidth;
    $this->scrollX = $scrollX;
  }

  public function setScrollX(int $scrollX): void {
    $this->scrollX = max(0, $scrollX);
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->x = $this->ancestor->geometry->borderLeft + $this->ancestor->geometry->paddingLeft;
    $this->geometry->y = $this->ancestor->geometry->borderTop + $this->ancestor->geometry->paddingTop;
    $this->geometry->width = $this->ancestor->geometry->innerWidth;
    $this->geometry->height = $this->lineHeight;
    $this->geometry->contentWidth = $this->tableContentWidth;
    $this->geometry->contentHeight = $this->lineHeight;
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateHeights(): void {
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $this->geometry->contentHeight = $this->lineHeight;
    $this->geometry->ascent = $this->geometry->fullHeight - $this->geometry->marginBottom;
    $this->geometry->descent = $this->geometry->marginBottom;
  }

  protected function layout(): void {
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
    $this->geometry->contentWidth = $this->tableContentWidth;
  }

}
