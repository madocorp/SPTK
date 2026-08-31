<?php

namespace SPTK\Widgets;

use SPTK\Core\RenderTarget;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;

/**
 * Shows a checkbox input
 */
class Checkbox extends Element {

  protected bool $checked = false;
  protected int $renderedColumns = 0;

  public function __construct(string $name = '', protected string $text = '') {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(1);
    $this->setPreferredColumns(mb_strlen($this->text) + 4);
  }

  public function setText(string $text): void {
    if ($this->text !== $text) {
      $this->invalidateRender();
    }
    $this->text = $text;
    $this->setPreferredColumns(mb_strlen($this->text) + 4);
    $this->invalidateRender();
  }

  public function toggle(): void {
    $this->checked = !$this->checked;
    $this->invalidateRender();
  }

  public function clear(): void {
    $this->checked = false;
    $this->invalidateRender();
  }

  public function setValue(bool $value = true): void {
    $this->checked = $value;
    $this->invalidateRender();
  }

  public function getValue(): bool {
    return $this->checked;
  }

  protected function paint(RenderTarget $target): void {
    $checkbox = '[' . ($this->checked ? 'X' : ' '). ']';
    $fg = $this->focused ? $this->theme->cursorFg : $this->theme->inverseFg;
    $bg = $this->focused ? $this->theme->cursorBg : $this->theme->inverseBg;
    $columns = min($this->frame->width, mb_strlen($this->text) + 4);
    $clearColumns = min($this->frame->width, max($this->renderedColumns, $columns));
    if ($clearColumns > 0) {
      $target->fill(new Rect($this->frame->x, $this->frame->y, $clearColumns, 1), ' ', $this->theme->fg, $this->theme->bg);
    }
    $target->write($this->frame->x, $this->frame->y, $checkbox, $fg, $bg);
    $target->write($this->frame->x + 4, $this->frame->y, mb_substr($this->text, 0, max(0, $this->frame->width - 4)), $this->theme->fg, $this->theme->bg);
    $this->renderedColumns = $columns;
  }

  public function handle(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    if (InputAction::activate($event, 'checkbox')) {
      $this->toggle();
      return true;
    }
    return false;
  }

}
