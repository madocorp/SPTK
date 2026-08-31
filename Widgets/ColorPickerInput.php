<?php

namespace SPTK\Widgets;

use SPTK\Core\InputEvent;

/**
 * Input that reports edits to a parent color selector.
 */
class ColorPickerInput extends Input {

  protected $onChange = null;

  public function setOnChange(callable $onChange): static {
    $this->onChange = $onChange;
    return $this;
  }

  public function handle(InputEvent $event): bool {
    $before = $this->text();
    $handled = parent::handle($event);
    if ($handled && $this->text() !== $before && $this->onChange !== null) {
      ($this->onChange)($this);
    }
    return $handled;
  }

}
