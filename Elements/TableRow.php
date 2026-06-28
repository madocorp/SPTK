<?php

namespace SPTK\Elements;

use \SPTK\Element;

class TableRow extends Element {

  protected int $y = 0;
  protected int $height = 0;
  protected int $dataRow = 0;

  public function setTablePosition(int $row, int $lineHeight, int $offsetY = 0): void {
    $this->dataRow = $row;
    $this->height = $lineHeight;
    $this->y = $offsetY + $row * $lineHeight;
  }

  public function getDataRow(): int {
    return $this->dataRow;
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->x = 0;
    $this->geometry->y = $this->y;
    $this->geometry->height = $this->height;
    $this->geometry->width = array_sum(array_map(fn($cell) => $cell->getCellWidth(), $this->descendants));
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

}
