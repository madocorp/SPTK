<?php

namespace SPTK\Widgets;

use SPTK\Core\RenderTarget;
use SPTK\Core\Element;

/**
 * Renders a single-row status message.
 */
class StatusBar extends Element {

  public function __construct(string $name = '', protected string $text = '') {
    parent::__construct($name);
    $this->gridAlignment = 'top-left';
  }

  public function setText(string $text): void {
    if ($this->text !== $text) {
      $this->text = $text;
      $this->invalidateRender();
    }
  }

  public function text(): string {
    return $this->text;
  }

  protected function paint(RenderTarget $target): void {
    $target->fillToWindowEdge($this->frame, ' ', '#000000', $this->theme->muted);
    $target->write($this->frame->x, $this->frame->y, mb_substr($this->text, 0, $this->frame->width), '#000000', $this->theme->muted);
  }

}
