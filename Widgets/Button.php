<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * One-line focusable command button.
 */
class Button extends Element {

  protected $onPress = null;
  protected ?string $hotKey = null;

  public function __construct(string $name = '', protected string $label = '') {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(1);
    $this->syncPreferredColumns();
  }

  public function setLabel(string $label): static {
    if ($this->label !== $label) {
      $this->invalidateRender();
    }
    $this->label = $label;
    $this->syncPreferredColumns();
    return $this;
  }

  public function setOnPress(callable $callback): static {
    $this->onPress = $callback;
    return $this;
  }

  public function setHotKey(?string $hotKey): static {
    if ($hotKey !== null && !preg_match('/^F([1-9]|1[0-2])$/', $hotKey)) {
      throw new \InvalidArgumentException("Button hotkey must be F1-F12.");
    }
    if ($this->hotKey !== $hotKey) {
      $this->invalidateRender();
    }
    $this->hotKey = $hotKey;
    $this->syncPreferredColumns();
    return $this;
  }

  public function setShortcut(?string $shortcut): static {
    return $this->setHotKey($shortcut);
  }

  public function hotKey(): ?string {
    return $this->hotKey;
  }

  public function matchesHotKey(string $key): bool {
    return $this->hotKey === $key;
  }

  public function press(): bool {
    if ($this->onPress === null) {
      return false;
    }
    ($this->onPress)($this);
    return true;
  }

  public function handle(InputEvent $event): bool {
    if (!InputAction::activate($event, 'button')) {
      return false;
    }
    return $this->press();
  }

  protected function paint(RenderTarget $target): void {
    $fg = $this->focused ? $this->theme->cursorFg : $this->theme->inverseFg;
    $bg = $this->focused ? $this->theme->cursorBg : $this->theme->inverseBg;
    $target->fill($this->frame, ' ', $fg, $bg);
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    $text = $this->buttonText();
    if (mb_strlen($text) > $this->frame->width) {
      $text = mb_substr($text, 0, $this->frame->width);
    }
    $x = $this->frame->x + max(0, intdiv($this->frame->width - mb_strlen($text), 2));
    if ($this->hotKey !== null) {
      $number = $this->hotKeyNumber();
      $target->write($x, $this->frame->y, $text, $fg, $bg);
      $target->write($x + 2, $this->frame->y, $number, $this->theme->hotkey, $bg);
      return;
    }
    $target->write($x, $this->frame->y, $text, $fg, $bg);
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(4, mb_strlen($this->buttonText())));
  }

  protected function buttonText(): string {
    if ($this->hotKey !== null) {
      return '[ ' . $this->hotKeyNumber() . ' ' . $this->label . ' ]';
    }
    return '[ ' . $this->label . ' ]';
  }

  protected function hotKeyNumber(): string {
    return $this->hotKey === null ? '' : substr($this->hotKey, 1);
  }

}
