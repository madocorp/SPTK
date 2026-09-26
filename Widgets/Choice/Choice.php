<?php

namespace SPTK\Widgets\Choice;

use SPTK\Core\{Color, ItemData, ItemViewport, ScrollIndicator, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Shares item selection, marker navigation, and painting for choice widgets. */
abstract class Choice extends Widget {

  use WidgetEventEmitter;

  private array $items = [];
  protected array $checked = [];
  private ItemViewport $viewport;
  private bool $active = false;

  /** Create a choice group with inherited style colors. */
  protected function __construct(private readonly bool $multiple, array $items, private readonly Color $fg, private readonly Color $bg, private readonly Color $cursorBg, private readonly Color $highlight) {
    $this->viewport = new ItemViewport();
    $this->setItems($items);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Replace item records and reset the highlighted position. */
  public function setItems(array $items): void {
    $normalized = ItemData::normalize($items, 'checked');
    $checked = [];
    foreach ($normalized as $item) {
      if ($item['checked']) {
        $checked[] = $item['value'];
      }
    }
    if (!$this->multiple && count($checked) > 1) {
      throw new \InvalidArgumentException('RadioButton accepts only one checked item.');
    }
    if (!$this->multiple && $checked === [] && $normalized !== []) {
      $checked = [$normalized[0]['value']];
    }
    $this->items = $normalized;
    $this->checked = $checked;
    $this->viewport->reset(count($normalized));
  }

  /** Return value, label, and checked state for every item. */
  public function items(): array {
    $items = [];
    foreach ($this->items as $item) {
      $item['checked'] = in_array($item['value'], $this->checked, true);
      $items[] = $item;
    }
    return $items;
  }

  /** Report whether keyboard interaction is active. */
  public function active(): bool {
    return $this->active;
  }

  /** Return the zero-based highlighted item index. */
  public function cursorPosition(): int {
    return $this->viewport->position();
  }

  /** Replace checked values atomically without emitting a user change event. */
  protected function setChecked(array $values): void {
    $known = array_column($this->items, 'value');
    foreach ($values as $value) {
      if (!is_string($value) || !in_array($value, $known, true)) {
        throw new \InvalidArgumentException('Unknown choice value.');
      }
    }
    if (!$this->multiple && count($values) > 1) {
      throw new \InvalidArgumentException('RadioButton accepts only one checked item.');
    }
    $checked = [];
    foreach ($known as $value) {
      if (in_array($value, $values, true)) {
        $checked[] = $value;
      }
    }
    $this->checked = $checked;
  }

  /** Return the background used by this choice tile. */
  public function background(): Color {
    return $this->bg;
  }

  /** Measure the widest marker and label in grid cells. */
  public function preferredWidth(): ?int {
    $width = 4;
    foreach ($this->items as $item) {
      $width = max($width, 4 + TextMetrics::width($item['label']));
    }
    return $width;
  }

  /** Reserve one row per item, including one row for an empty group. */
  public function preferredHeight(): ?int {
    return max(1, count($this->items));
  }

  /** Paint markers, clipped labels, and vertical scroll indicators. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->fg, $this->bg);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return;
    }
    $this->viewport->setHeight($writer->height());
    $scroll = $this->viewport->scroll();
    for ($y = 0; $y < $writer->height(); $y++) {
      $index = $scroll + $y;
      $item = $this->items[$index] ?? null;
      if ($item === null) {
        continue;
      }
      $checked = in_array($item['value'], $this->checked, true);
      $marker = $this->multiple ? ($checked ? '[X]' : '[ ]') : ($checked ? '(O)' : '( )');
      $cursorBg = $this->active && $index === $this->viewport->position() ? $this->cursorBg : $this->bg;
      $writer->write(0, $y, $marker, $this->fg, $cursorBg);
      $writer->write(3, $y, ' ' . $item['label'], $this->fg, $this->bg);
    }
    $this->indicator($writer, ScrollIndicator::label($scroll, $writer->height(), '▲'), 0);
    $below = max(0, count($this->items) - $scroll - $writer->height());
    $this->indicator($writer, ScrollIndicator::label($below, $writer->height(), '▼'), $writer->height() - 1);
  }

  /** Move the marker or toggle its checked state. */
  public function handleInput(mixed $event): bool {
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
    if ($key === SDL::KEY_LEFT || $key === SDL::KEY_RIGHT) {
      return false;
    }
    if ($key === SDL::KEY_SPACE) {
      if (!$event->key->repeat) {
        $this->toggleCurrent();
      }
      return true;
    }
    if (in_array($key, [SDL::KEY_UP, SDL::KEY_DOWN, SDL::KEY_HOME, SDL::KEY_END, SDL::KEY_PAGEUP, SDL::KEY_PAGEDOWN], true)) {
      $this->viewport->move($key);
      return true;
    }
    return false;
  }

  /** Make Escape keep changed choices. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

  /** Change only the highlighted choice and notify on actual user changes. */
  private function toggleCurrent(): void {
    $item = $this->items[$this->viewport->position()] ?? null;
    if ($item === null) {
      return;
    }
    $before = $this->checked;
    $value = $item['value'];
    if ($this->multiple) {
      $this->setChecked(in_array($value, $before, true) ? array_values(array_diff($before, [$value])) : [...$before, $value]);
    } else {
      $this->setChecked([$value]);
    }
    if ($before !== $this->checked) {
      $this->emit('change');
    }
  }

  /** Draw an edge-aligned scroll mark without covering the choice marker. */
  private function indicator(GridWriter $writer, string $label, int $y): void {
    if ($label === '' || $writer->width() < 4) {
      return;
    }
    if (TextMetrics::width($label) > $writer->width() - 3) {
      $label = preg_replace('/[0-9]/', '', $label);
    }
    if (TextMetrics::width($label) > $writer->width() - 3) {
      return;
    }
    $writer->write($writer->width() - TextMetrics::width($label), $y, $label, $this->bg, $this->highlight);
  }

  /** Mark the choice group active after tile activation. */
  private function activate(): void {
    $this->active = true;
  }

  /** Remove its marker highlight after leaving input mode. */
  private function deactivate(): void {
    $this->active = false;
  }

}
