<?php

namespace SPTK\Elements;

use \SPTK\Element;

class SubMenu extends Element {

  public function showMenuBox($name, $x, $y, $closeOthers) {
    foreach ($this->descendants as $element) {
      if ($element->type == 'MenuBox') {
        if ($element->belongsTo == $name) {
          $element->show();
          $element->recalculateGeometry();
          $element->gotoSelected();
          $element->activateItem();
          $element->style->set('x', "{$x}px");
          $element->style->set('y', "{$y}px");
          $element->raise();
        } else if ($closeOthers) {
          $element->hide();
        }
      }
    }
    Element::refresh();
  }

  public function closeMenuBoxes() {
    foreach ($this->descendants as $element) {
      $element->hide();
    }
    Element::refresh();
  }

}
