<?php

namespace SPTK;

class SubMenu extends Box {

  public function showMenuBox($id, $x, $y, $closeOthers) {
    foreach ($this->descendants as $element) {
      if ($element->type == 'MenuBox') {
        if ($element->belongsTo == $id) {
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
