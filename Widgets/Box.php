<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Wraps optional background and border decoration around child content.
 */
class Box extends Element {

  protected Color|string|int|null $background = null;
  protected string $border = 'none';
  protected int $borderWidth = 1;
  protected Color|string|int|null $borderColor = null;

  public function __construct(string $name = '', ?Element $child = null, array $options = []) {
    parent::__construct($name);
    if (array_key_exists('background', $options)) {
      $this->background = $options['background'];
    }
    $this->border = (string)($options['border'] ?? 'none');
    $this->borderWidth = max(1, (int)($options['borderWidth'] ?? 1));
    $this->borderColor = $options['borderColor'] ?? null;
    if ($child !== null) {
      $this->add($child);
    }
  }

  public function setBackground(Color|string|int|null $background): static {
    $this->background = $background;
    $this->invalidateRender();
    return $this;
  }

  public function setBorder(string $mode, int $width = 1, Color|string|int|null $color = null): static {
    if (!in_array($mode, ['none', 'inside', 'outside'], true)) {
      throw new \InvalidArgumentException("Invalid border mode: {$mode}");
    }
    $this->border = $mode;
    $this->borderWidth = max(1, $width);
    $this->borderColor = $color;
    $this->invalidateLayout();
    return $this;
  }

  public function surfaceBackground(): Color|string|int|null {
    return $this->transparent() ? null : ($this->background ?? $this->theme->bg);
  }

  public function layout(): void {
    $content = $this->border === 'inside' ? $this->frame->inset($this->borderWidth, $this->borderWidth) : $this->frame;
    foreach ($this->children as $child) {
      $child->setFrame($content);
    }
  }

  protected function paint(RenderTarget $target): void {
    if (!$this->transparent()) {
      $target->fill($this->frame, ' ', $this->theme->fg, $this->background ?? $this->theme->bg);
    }
    if ($this->border === 'inside') {
      $target->drawRect($this->frame, $this->borderColor ?? $this->theme->border, $this->borderWidth);
    } else if ($this->border === 'outside') {
      $target->drawOuterRect($this->frame, $this->borderColor ?? $this->theme->border, $this->borderWidth);
    }
  }

  protected function transparent(): bool {
    return $this->background === 'transparent'
      || ($this->background instanceof Color && $this->background->a === 0);
  }

}
