<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\RenderTarget;

/**
 * Filled color preview strip.
 */
class ColorPreview extends Element {

  protected string $color;

  public function __construct(string $name = '', string $color = '#000000') {
    parent::__construct($name);
    $this->setPreferredRows(1);
    $this->color = $this->normalizeColor($color);
  }

  public function setColor(string $color): static {
    $color = $this->normalizeColor($color);
    if ($this->color !== $color) {
      $this->color = $color;
      $this->invalidateRender();
    }
    return $this;
  }

  public function color(): string {
    return $this->color;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', '#000000', $this->color);
  }

  protected function normalizeColor(string $value): string {
    return Color::from($value)->hex();
  }

}
