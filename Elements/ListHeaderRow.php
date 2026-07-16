<?php

namespace SPTK\Elements;

use \SPTK\Element;

class ListHeaderRow extends Element {

  protected function init(): void {
    new Element($this, null, null, 'ItemLeft');
  }

  public function columnWidths(): array|false {
    $widths = [];
    foreach ($this->descendants as $descendant) {
      if ($descendant->getType() !== 'Header') {
        continue;
      }
      foreach ($descendant->getClass() as $class) {
        if (preg_match('/^w(\d+(?:\.\d+)?)$/', $class, $matches)) {
          $widths[] = (float)$matches[1];
          break;
        }
      }
    }
    return empty($widths) ? false : $widths;
  }

  protected function calculateWidths(): void {
    parent::calculateWidths();
    $list = $this->nextListBox();
    $widths = $this->columnWidths();
    if ($list === false || $widths === false) {
      return;
    }
    $this->setElementWidth($this->nthChild(0), $list->gridTextOffset());
    $pixelWidths = $list->gridColumnPixelWidths($widths);
    $column = 0;
    foreach ($this->descendants as $descendant) {
      if ($descendant->getType() === 'Header') {
        $this->setElementWidth($descendant, $pixelWidths[$column] ?? 0);
        $column++;
      }
    }
  }

  protected function nextListBox(): ListBox|false {
    $foundSelf = false;
    foreach ($this->ancestor?->getDescendants() ?? [] as $descendant) {
      if ($descendant === $this) {
        $foundSelf = true;
        continue;
      }
      if (!$foundSelf || !$descendant->isDisplayed()) {
        continue;
      }
      if ($descendant instanceof ListBox) {
        return $descendant;
      }
      if ($descendant->getType() !== 'Space' && $descendant->getType() !== 'NL') {
        return false;
      }
    }
    return false;
  }

  protected function setElementWidth(Element|false $element, int $width): void {
    if ($element === false) {
      return;
    }
    $geometry = $element->getGeometry();
    $geometry->width = max(0, $width);
    $geometry->setDerivedWidths();
  }

}
