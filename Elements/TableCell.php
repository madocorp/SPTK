<?php

namespace SPTK\Elements;

use \SPTK\Element;

class TableCell extends Element {

  protected int $x = 0;
  protected int $width = 0;
  protected int $height = 0;
  protected mixed $cellValue = null;

  public function setCell(int $x, int $width, int $height, mixed $value): void {
    $this->setCellGeometry($x, $width, $height);
    $this->setCellValue($value);
  }

  public function setCellGeometry(int $x, int $width, int $height): void {
    $this->x = $x;
    $this->width = $width;
    $this->height = $height;
  }

  public function setCellValue(mixed $value): void {
    if ($this->cellValue === $value && isset($this->descendants[0])) {
      return;
    }
    $this->cellValue = $value;
    $text = $value === null ? '' : (string)$value;
    if (!isset($this->descendants[0])) {
      new Word($this);
    }
    $this->descendants[0]->setValue($text);
  }

  public function getCellWidth(): int {
    return $this->width;
  }

  public function getValue(): mixed {
    return $this->cellValue;
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->x = $this->x;
    $this->geometry->y = 0;
    $this->geometry->width = $this->width;
    $this->geometry->height = $this->height;
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

}
