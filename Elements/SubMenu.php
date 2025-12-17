<?php

namespace SPTK;

class SubMenu extends Element {

  public function showMenuBox($name, $x, $y, $closeOthers) {
    foreach ($this->descendants as $element) {
      if ($element->type == 'MenuBox') {
        if ($element->belongsTo == $name) {
          $element->activateMenuBoxItem();
          $element->style->set('x', "{$x}px");
          $element->style->set('y', "{$y}px");
          $element->raise();
          $element->show();
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
