<?php

namespace SPTK;

class Box extends Element {

  public function draw() {
    $color = $this->style->get('backgroundColor');
    $width = $this->geometry->width - $this->geometry->borderLeft - $this->geometry->borderRight;
    $height = $this->geometry->height - $this->geometry->borderTop - $this->geometry->borderBottom;
    $this->texture = new Texture($this->renderer, $width, $height, $color);
  }

}
