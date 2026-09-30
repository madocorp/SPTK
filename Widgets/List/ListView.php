<?php

namespace SPTK\Widgets\List;

use SPTK\Core\{Color, ItemData, ItemViewport, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Displays a scrollable list with selection, prefix filtering, and optional ordering. */
final class ListView extends Widget {

  use WidgetEventEmitter;
  use InputHandling;
  use Tips;

  private array $items = [];
  private array $visible = [];
  private ItemViewport $viewport;
  private View $view;
  private Redraw $redraw;
  private int $cursorItem = 0;
  private string $query = '';
  private bool $active = false;

  /** Create a list and register its activation lifecycle. */
  public function __construct(
    array $items = [],
    private readonly bool $multiple = false,
    private readonly bool $filterable = true,
    private readonly bool $searchable = true,
    private readonly bool $reorderable = false,
    private readonly Style $style = new Style(),
    ?string $title = null,
  ) {
    $this->viewport = new ItemViewport();
    $this->redraw = new Redraw();
    $this->view = new View($style, $this->viewport, $this->redraw, $title);
    $this->setItems($items);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Replace items and reset filtering, cursor, and scroll. */
  public function setItems(array $items): void {
    $normalized = ItemData::normalize($items, 'selected');
    $selected = array_keys(array_filter(array_column($normalized, 'selected')));
    if (!$this->multiple && count($selected) > 1) {
      throw new \InvalidArgumentException('Single-selection List accepts only one selected item.');
    }
    $this->items = $normalized;
    $this->query = '';
    $this->visible = array_keys($normalized);
    $this->viewport->reset(count($normalized));
    $this->cursorItem = $selected[0] ?? 0;
    $this->viewport->setPosition($this->cursorItem);
    $this->redraw->full();
  }

  /** Return item records with their current selected state. */
  public function items(): array {
    $items = $this->items;
    if (!$this->multiple) {
      foreach ($items as $index => &$item) {
        $item['selected'] = $index === $this->cursorPosition() && $this->activeValue() !== null;
      }
      unset($item);
    }
    return $items;
  }

  /** Return all values in their current display order. */
  public function values(): array {
    return array_column($this->items, 'value');
  }

  /** Return the value under the cursor or null when no row is visible. */
  public function activeValue(): ?string {
    $index = $this->visible[$this->viewport->position()] ?? null;
    return $index === null ? null : $this->items[$index]['value'];
  }

  /** Return one current value or all selected values in item order. */
  public function getValue(): string|array|null {
    if (!$this->multiple) {
      return $this->activeValue();
    }
    return array_column(array_filter($this->items, $this->isSelected(...)), 'value');
  }

  /** Set selection without notifying user-change subscribers. */
  public function setValue(string|array $value): void {
    $values = $this->multiple ? (is_array($value) ? $value : [$value]) : (is_string($value) ? [$value] : $value);
    if (!$this->multiple && count($values) !== 1) {
      throw new \InvalidArgumentException('Single-selection List needs one value.');
    }
    foreach ($values as $candidate) {
      if (!is_string($candidate) || !in_array($candidate, $this->values(), true)) {
        throw new \InvalidArgumentException('Unknown list value.');
      }
    }
    if (!$this->multiple) {
      $this->redraw->full();
      $this->setFilter('');
      $this->cursorItem = array_search($values[0], $this->values(), true);
      $this->viewport->setPosition($this->cursorItem);
      return;
    }
    foreach ($this->items as &$item) {
      $item['selected'] = in_array($item['value'], $values, true);
    }
    unset($item);
    $this->redraw->full();
  }

  /** Report whether the list has keyboard focus. */
  public function active(): bool {
    return $this->active;
  }

  /** Return the zero-based item index under the cursor. */
  public function cursorPosition(): int {
    return $this->visible[$this->viewport->position()] ?? $this->cursorItem;
  }

  /** Return the current prefix query. */
  public function filter(): string {
    return $this->query;
  }

  /** Apply a prefix query without emitting an input event. */
  public function setFilter(string $query): void {
    if (!mb_check_encoding($query, 'UTF-8')) {
      throw new \InvalidArgumentException('List filter must be valid UTF-8.');
    }
    if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $query)) {
      throw new \InvalidArgumentException('List filter must be printable text.');
    }
    $query = preg_replace('/[\r\n\t]+/u', ' ', $query);
    if ($this->query === $query) {
      return;
    }
    $oldIndex = $this->cursorPosition();
    $this->redraw->full();
    $this->query = $query;
    $matches = ItemSearch::matchingIndices($this->items, $query);
    $this->visible = $query !== '' && ($this->filterable || $matches === []) ? $matches : array_keys($this->items);
    $position = array_search($oldIndex, $this->visible, true);
    $this->viewport->setCount(count($this->visible));
    $target = $position === false ? 0 : $position;
    if (!$this->filterable && $query !== '' && $matches !== []) {
      $target = array_search($matches[0], $this->visible, true);
    }
    $this->viewport->setPosition($target);
    if ($this->visible !== []) {
      $this->cursorItem = $this->visible[$this->viewport->position()];
    }
  }

  /** Use the tile background for inactive cells. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Measure the widest item label. */
  public function preferredWidth(): ?int {
    $width = max(12, $this->view->titleWidth());
    foreach ($this->items as $item) {
      $width = max($width, TextMetrics::width($item['label']));
    }
    return $width;
  }

  /** Reserve item or empty-state rows plus the optional fixed title. */
  public function preferredHeight(): ?int {
    return max(1, count($this->items)) + $this->view->titleHeight();
  }

  /** Paint visible rows, search matches, and inverted scroll arrows. */
  public function paint(GridWriter $writer): void {
    $this->view->paint($writer, $this->items, $this->visible, $this->query, $this->active, $this->multiple);
  }

  /** Paint only cursor or selection rows while the viewport stays fixed. */
  public function paintUpdate(GridWriter $writer): bool {
    return $this->view->paintUpdate($writer, $this->items, $this->visible, $this->query, $this->active, $this->multiple);
  }

  /** Apply a user operation and notify value and order changes. */
  private function changeValue(callable $operation, mixed ...$arguments): void {
    $before = $this->getValue();
    $order = $this->reorderable ? $this->values() : null;
    $operation(...$arguments);
    $reordered = $order !== null && $order !== $this->values();
    if ($before !== $this->getValue()) {
      $this->emit('change');
    }
    if ($reordered) {
      $this->emit('reorder');
    }
  }

  /** Append valid text to the current prefix query. */
  private function appendQuery(string $text): void {
    $this->setFilter($this->query . $text);
  }

  /** Toggle the selected flag at the cursor. */
  private function toggleCurrent(): void {
    $index = $this->visible[$this->viewport->position()] ?? null;
    if ($index !== null) {
      $this->items[$index]['selected'] = !$this->items[$index]['selected'];
      $this->redraw->current($this->viewport->position(), $this->viewport->scroll());
    }
  }

  /** Move the cursor or reorder one row with Shift and an arrow. */
  private function move(int $key, int $mod): void {
    if (($mod & SDL::MOD_SHIFT) !== 0 && ($key === SDL::KEY_UP || $key === SDL::KEY_DOWN)) {
      $this->redraw->full();
      if ($this->reorderable && $this->query === '') {
        $from = $this->viewport->position();
        $to = max(0, min(count($this->items) - 1, $from + ($key === SDL::KEY_DOWN ? 1 : -1)));
        if ($to !== $from) {
          $item = array_splice($this->items, $from, 1);
          array_splice($this->items, $to, 0, $item);
          $this->viewport->setPosition($to);
          $this->cursorItem = $to;
        }
      }
      return;
    }
    $oldPosition = $this->viewport->position();
    $oldScroll = $this->viewport->scroll();
    $this->viewport->move($key);
    $this->redraw->moved($oldPosition, $oldScroll, $this->viewport->position(), $this->viewport->scroll());
    if ($this->visible !== []) {
      $this->cursorItem = $this->visible[$this->viewport->position()];
    }
  }

  /** Return a record's selected flag for value collection. */
  private function isSelected(array $item): bool {
    return $item['selected'];
  }

  /** Mark the list active after tile activation. */
  private function activate(): void {
    $this->active = true;
    $this->redraw->full();
  }

  /** Clear transient search state when leaving input mode. */
  private function deactivate(): void {
    $this->active = false;
    $this->redraw->full();
    $this->changeValue($this->setFilter(...), '');
  }

}
