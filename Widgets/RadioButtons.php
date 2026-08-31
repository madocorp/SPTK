<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Shows an exclusive group of radio button choices.
 */
class RadioButtons extends Element {

  protected array $items = [];
  protected int|string|null $selectedKey = null;
  protected bool $vertical = false;
  protected array $renderedRects = [];

  public function __construct(string $name = '', array $items = [], array $options = []) {
    parent::__construct($name);
    $this->focusable = true;
    $this->setOptions($options);
    $this->setItems($items);
  }

  public function setOptions(array $options): static {
    if (array_key_exists('vertical', $options)) {
      $this->vertical = (bool)$options['vertical'];
      $this->invalidateLayout();
      $this->invalidateRender();
    }
    return $this;
  }

  public function setItems(array $items): static {
    $this->items = [];
    foreach ($items as $key => $text) {
      $this->items[$key] = (string)$text;
    }
    if ($this->items === []) {
      $this->selectedKey = null;
    } else if ($this->selectedKey === null || !array_key_exists($this->selectedKey, $this->items)) {
      $this->selectedKey = array_key_first($this->items);
    }
    $this->syncPreferredColumns();
    $this->invalidateLayout();
    $this->invalidateRender();
    return $this;
  }

  public function setValue(mixed $value): static {
    $key = $this->keyForValue($value);
    if ($key !== null && $this->selectedKey !== $key) {
      $this->selectedKey = $key;
      $this->invalidateRender();
    }
    return $this;
  }

  public function getValue(): mixed {
    if ($this->selectedKey === null || !array_key_exists($this->selectedKey, $this->items)) {
      return null;
    }
    return is_int($this->selectedKey) ? $this->items[$this->selectedKey] : $this->selectedKey;
  }

  public function preferredRows(): int {
    return $this->vertical ? max(1, count($this->items)) : parent::preferredRows();
  }

  public function preferredRowsForColumns(int $columns): int {
    return max(1, count($this->layoutButtons(max(1, $columns))));
  }

  public function handle(InputEvent $event): bool {
    if ($event->type !== 'key' || $this->items === []) {
      return false;
    }
    if (InputAction::right($event, 'radio') || InputAction::down($event, 'radio')) {
      $this->moveSelection(1);
      return true;
    }
    if (InputAction::activate($event, 'radio')) {
      return true;
    }
    if (InputAction::left($event, 'radio') || InputAction::up($event, 'radio')) {
      $this->moveSelection(-1);
      return true;
    }
    if (InputAction::home($event, 'radio')) {
      return $this->selectIndex(0);
    }
    if (InputAction::end($event, 'radio')) {
      return $this->selectIndex(count($this->items) - 1);
    }
    return false;
  }

  protected function paint(RenderTarget $target): void {
    foreach ($this->renderedRects as $rect) {
      $target->fill($rect, ' ', $this->theme->fg, $this->theme->bg);
    }
    $this->renderedRects = [];
    foreach ($this->layoutButtons($this->frame->width) as $button) {
      if ($button['y'] >= $this->frame->height) {
        break;
      }
      $x = $this->frame->x + $button['x'];
      $y = $this->frame->y + $button['y'];
      $width = min($button['width'], max(0, $this->frame->right() - $x));
      if ($width <= 0) {
        continue;
      }
      $rect = new Rect($x, $y, $width, 1);
      $target->fill($rect, ' ', $this->theme->fg, $this->theme->bg);
      $selected = $button['key'] === $this->selectedKey;
      $marker = '(' . ($selected ? 'o' : ' ') . ')';
      $fg = $selected ? ($this->focused ? $this->theme->cursorFg : $this->theme->inverseFg) : $this->theme->inverseFg;
      $bg = $selected ? ($this->focused ? $this->theme->cursorBg : $this->theme->inverseBg) : $this->theme->inverseBg;
      $target->write($x, $y, mb_substr($marker, 0, $width), $fg, $bg);
      if ($width > 4) {
        $target->write($x + 4, $y, mb_substr($button['text'], 0, $width - 4), $this->theme->fg, $this->theme->bg);
      }
      $this->renderedRects[] = $rect;
    }
  }

  protected function layoutButtons(int $columns): array {
    $buttons = $this->buttons();
    if ($buttons === []) {
      return [];
    }
    if ($this->vertical || $this->inlineColumns() > $columns) {
      foreach ($buttons as $i => $button) {
        $buttons[$i]['x'] = 0;
        $buttons[$i]['y'] = $i;
      }
      return $buttons;
    }
    $x = 0;
    foreach ($buttons as $i => $button) {
      $buttons[$i]['x'] = $x;
      $buttons[$i]['y'] = 0;
      $x += $button['width'] + 2;
    }
    return $buttons;
  }

  protected function buttons(): array {
    $buttons = [];
    foreach ($this->items as $key => $text) {
      $buttons[] = [
        'key' => $key,
        'text' => $text,
        'width' => mb_strlen($text) + 4,
      ];
    }
    return $buttons;
  }

  protected function inlineColumns(): int {
    $width = 0;
    foreach ($this->buttons() as $i => $button) {
      $width += $button['width'];
      if ($i > 0) {
        $width += 2;
      }
    }
    return $width;
  }

  protected function syncPreferredColumns(): void {
    $width = 0;
    foreach ($this->buttons() as $button) {
      $width = max($width, $button['width']);
    }
    $this->setPreferredColumns($this->vertical ? $width : max($width, $this->inlineColumns()));
  }

  protected function keyForValue(mixed $value): int|string|null {
    if ((is_int($value) || is_string($value)) && array_key_exists($value, $this->items)) {
      return $value;
    }
    foreach ($this->items as $key => $text) {
      if (is_int($key) && $text === $value) {
        return $key;
      }
    }
    return null;
  }

  protected function moveSelection(int $delta): void {
    $keys = array_keys($this->items);
    $index = array_search($this->selectedKey, $keys, true);
    if ($index === false) {
      $index = 0;
    }
    $count = count($keys);
    $index = ($index + $delta + $count) % $count;
    $this->selectedKey = $keys[$index];
    $this->invalidateRender();
  }

  protected function selectIndex(int $index): bool {
    $keys = array_keys($this->items);
    if (!isset($keys[$index])) {
      return false;
    }
    $this->selectedKey = $keys[$index];
    $this->invalidateRender();
    return true;
  }

}
