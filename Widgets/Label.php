<?php

namespace SPTK\Widgets;

use SPTK\Core\RenderTarget;
use SPTK\Core\Element;

/**
 * Renders a single line of static text inside its frame.
 */
class Label extends Element {

  public function __construct(string $name = '', protected string $text = '') {
    parent::__construct($name);
    $this->setPreferredRows(1);
    $this->setPreferredColumns(mb_strlen($this->text));
  }

  public function setText(string $text): void {
    if ($this->text !== $text) {
      $this->invalidateRender();
    }
    $this->text = $text;
    $this->setPreferredColumns(mb_strlen($this->text));
  }

  protected function paint(RenderTarget $target): void {
    $target->write($this->frame->x, $this->frame->y, mb_substr($this->text, 0, $this->frame->width), $this->theme->fg, $this->theme->bg);
  }

}
