<?php

namespace SPTK\Widgets;

use SPTK\Core\RenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\Element;

/**
 * Paints a dialog box background, optional border, and child content.
 */
class DialogBox extends Element {

  protected bool $border = false;
  protected string $title = '';

  public function __construct(string $name = '', bool|array $options = false, string $title = '') {
    parent::__construct($name);
    if (is_array($options)) {
      $this->border = (bool)($options['border'] ?? false);
      $this->title = (string)($options['title'] ?? '');
    } else {
      $this->border = $options;
      $this->title = $title;
    }
  }

  public function layout(): void {
    $inner = $this->border ? $this->frame->inset(1, 1) : $this->frame;
    foreach ($this->children as $child) {
      $child->setFrame($inner);
    }
  }

  public function focusScope(): string {
    return 'dialog';
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    if (!$this->border || $this->frame->width < 2 || $this->frame->height < 2) {
      return;
    }
    if ($this->title !== '') {
      $target->fill(new Rect($this->frame->x, $this->frame->y, $this->frame->width, 1), ' ', $this->theme->inverseFg, $this->theme->inverseBg);
    }
    $target->drawRect($this->frame, $this->title !== '' ? $this->theme->inverseBg : $this->theme->border);
    if ($this->title !== '') {
      $target->write($this->frame->x + 1, $this->frame->y, ' ' . mb_substr($this->title, 0, max(0, $this->frame->width - 3)) . ' ', $this->theme->inverseFg, $this->theme->inverseBg);
    }
  }

}
