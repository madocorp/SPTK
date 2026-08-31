<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;

/**
 * Displays a scrollable, searchable list of formatted rows.
 */
class ListView extends Element {

  protected int $cursor = 0;
  protected int $scroll = 0;
  protected array $items = [];
  protected string $search = '';
  protected array $matches = [];
  protected bool $searchable = false;
  protected bool $filterable = false;
  protected bool $reorderable = false;
  protected bool $selectOnReturn = true;
  protected bool $selectionOrder = false;
  protected array $selectedOrder = [];
  protected int $paddingX = 1;
  protected Color|string|int|null $fieldFg = null;
  protected Color|string|int|null $fieldBg = null;
  protected $onChange = null;
  protected $onSelect = null;

  public function __construct(string $name = '', array $items = [], array $options = []) {
    parent::__construct($name);
    $this->focusable = true;
    $this->setOptions($options);
    $this->setItems($items);
  }

  public function setOptions(array $options): static {
    if (array_key_exists('searchable', $options)) {
      $this->searchable = (bool)$options['searchable'];
    }
    if (array_key_exists('filterable', $options)) {
      $this->filterable = (bool)$options['filterable'];
      if ($this->filterable) {
        $this->searchable = true;
      }
    }
    if (array_key_exists('reorderable', $options)) {
      $this->reorderable = (bool)$options['reorderable'];
    }
    if (array_key_exists('selectOnReturn', $options)) {
      $this->selectOnReturn = (bool)$options['selectOnReturn'];
    }
    if (array_key_exists('selectionOrder', $options)) {
      $this->selectionOrder = (bool)$options['selectionOrder'];
    }
    if (array_key_exists('paddingX', $options)) {
      $this->paddingX = max(0, (int)$options['paddingX']);
    } else if (array_key_exists('padding', $options)) {
      $this->paddingX = max(0, (int)$options['padding']);
    }
    if (array_key_exists('onChange', $options) && is_callable($options['onChange'])) {
      $this->onChange = $options['onChange'];
    }
    if (array_key_exists('onSelect', $options) && is_callable($options['onSelect'])) {
      $this->onSelect = $options['onSelect'];
    }
    return $this;
  }

  public function items(): array {
    return $this->items;
  }

  public function item(int $index): ?ListItem {
    return $this->items[$index] ?? null;
  }

  public function addItem(ListItem|array|string $item, ?int $index = null): ListItem {
    $item = $this->normalizeItem($item);
    if ($index === null || $index < 0 || $index >= count($this->items)) {
      $this->items[] = $item;
    } else {
      array_splice($this->items, $index, 0, [$item]);
    }
    $this->refreshItems();
    return $item;
  }

  public function setItems(array $items): static {
    $this->items = [];
    $this->cursor = 0;
    $this->scroll = 0;
    $this->search = '';
    $this->matches = [];
    $this->selectedOrder = [];
    foreach ($items as $item) {
      $this->items[] = $this->normalizeItem($item);
    }
    foreach ($this->items as $item) {
      if ($item->selected()) {
        $this->rememberSelected($item);
      }
    }
    $this->refreshItems(false);
    return $this;
  }

  public function cursor(): int {
    return $this->cursor;
  }

  public function setCursor(int $cursor): static {
    $this->moveCursorTo($cursor);
    $this->syncScroll();
    $this->invalidateRender();
    return $this;
  }

  public function activeItem(): ?ListItem {
    return $this->items[$this->cursor] ?? null;
  }

  public function value(): mixed {
    return $this->activeItem()?->value();
  }

  public function selectedValues(): array {
    if ($this->selectionOrder) {
      $selected = [];
      foreach ($this->selectedOrder as $id) {
        foreach ($this->items as $item) {
          if (spl_object_id($item) === $id && $item->selectable() === true && $item->selected()) {
            $selected[] = $item->value();
            break;
          }
        }
      }
      return $selected;
    }
    $selected = [];
    foreach ($this->items as $item) {
      if ($item->selectable() === true && $item->selected()) {
        $selected[] = $item->value();
      }
    }
    return $selected;
  }

  public function values(): array {
    return array_map(fn(ListItem $item): mixed => $item->value(), $this->items);
  }

  public function setSelectedValues(array $values): static {
    $this->selectedOrder = [];
    foreach ($this->items as $item) {
      $item->deselect();
    }
    foreach ($values as $value) {
      foreach ($this->items as $item) {
        if ($item->selectable() === true && $item->value() === $value && !$item->selected()) {
          $this->selectItem($item);
          break;
        }
      }
    }
    $this->invalidateRender();
    return $this;
  }

  public function selectAll(): static {
    $values = [];
    foreach ($this->items as $item) {
      if ($item->selectable() === true) {
        $values[] = $item->value();
      }
    }
    return $this->setSelectedValues($values);
  }

  public function clearSelection(): static {
    return $this->setSelectedValues([]);
  }

  public function setFieldColors(Color|string|int|null $fg = null, Color|string|int|null $bg = null): static {
    $this->fieldFg = $fg;
    $this->fieldBg = $bg;
    $this->invalidateRender();
    return $this;
  }

  public function setPaddingX(int $padding): static {
    $this->paddingX = max(0, $padding);
    $this->invalidateRender();
    return $this;
  }

  public function handle(InputEvent $event): bool {
    if (!in_array($event->type, ['key', 'text'], true)) {
      return false;
    }
    $ctrl = !empty($event->modifiers['ctrl']);
    if ($event->type === 'text') {
      return $this->handleText($event->text);
    }
    $shift = !empty($event->modifiers['shift']);
    if ($shift && InputAction::up($event, 'list')) {
      return $this->moveActiveItem(-1);
    }
    if ($shift && InputAction::down($event, 'list')) {
      return $this->moveActiveItem(1);
    }
    if (InputAction::down($event, 'list')) {
      $this->moveVisibleCursor(1);
    } else if (InputAction::up($event, 'list')) {
      $this->moveVisibleCursor(-1);
    } else if (InputAction::home($event, 'list')) {
      $visible = $this->visibleIndexes();
      $this->moveCursorTo($visible[0] ?? 0);
    } else if (InputAction::end($event, 'list')) {
      $visible = $this->visibleIndexes();
      $this->moveCursorTo($visible[count($visible) - 1] ?? 0);
    } else if (InputAction::pageDown($event, 'list')) {
      if ($ctrl) {
        $visible = $this->visibleIndexes();
        $this->moveCursorTo($visible[count($visible) - 1] ?? 0);
      } else {
        $this->moveVisibleCursor(max(1, $this->visibleRows() - 1));
      }
    } else if (InputAction::pageUp($event, 'list')) {
      if ($ctrl) {
        $visible = $this->visibleIndexes();
        $this->moveCursorTo($visible[0] ?? 0);
      } else {
        $this->moveVisibleCursor(-max(1, $this->visibleRows() - 1));
      }
    } else if (InputAction::backspace($event, 'list')) {
      if (!$this->handleBackspace()) {
        return false;
      }
    } else if (InputAction::delete($event, 'list')) {
      if (!$this->handleDelete()) {
        return false;
      }
    } else if (InputAction::newline($event, 'list')) {
      $item = $this->activeItem();
      if ($item !== null && $item->selectable() !== false && !$this->selectOnReturn) {
        return false;
      }
      return $this->selectActiveItem();
    } else if (InputAction::select($event, 'list')) {
      return $this->selectActiveItem();
    } else {
      return false;
    }
    $this->syncScroll();
    $this->invalidateRender();
    return true;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->fieldFg(), $this->fieldBg());
    $this->clampCursor();
    $this->syncScroll();
    $indexes = $this->visibleIndexes();
    $leftWidth = $this->leftWidth($indexes);
    $rightWidth = $this->rightWidth($indexes);
    for ($row = 0; $row < $this->visibleRows(); $row++) {
      $index = $indexes[$this->scroll + $row] ?? null;
      if ($index === null || !isset($this->items[$index])) {
        break;
      }
      $this->paintItem($target, $this->items[$index], $index, $this->frame->y + $row, $leftWidth, $rightWidth);
    }
    $this->paintScrollbar($target);
  }

  protected function paintItem(RenderTarget $target, ListItem $item, int $index, int $y, int $leftWidth, int $rightWidth): void {
    $active = $index === $this->cursor;
    $fg = $active ? ($this->focused ? $this->theme->cursorFg : ($item->enabled() ? $this->fieldFg() : $this->theme->muted)) : ($item->enabled() ? $this->fieldFg() : $this->theme->muted);
    $bg = $active ? ($this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg) : $this->fieldBg();
    $row = new Rect($this->frame->x, $y, $this->frame->width, 1);
    $target->fill($row, ' ', $fg, $bg);
    $content = $this->contentRect();
    $x = $content->x;
    if ($leftWidth > 0) {
      $target->write($x, $y, mb_substr(str_pad($item->left(), $leftWidth), 0, $leftWidth), $this->theme->markerFg, $bg);
      $x += $leftWidth;
    }
    if ($item->prefix() !== '') {
      $prefix = $item->prefix() . $item->prefixSeparator();
      $prefix = mb_substr($prefix, 0, max(0, $content->right() - $x));
      $target->write($x, $y, $prefix, $this->theme->markerFg, $bg);
      $x += mb_strlen($prefix);
    }
    $right = $item->right();
    $reservedRight = max($rightWidth, $right === '' ? 0 : mb_strlen($right));
    $textLimit = max(0, $content->right() - $x - $reservedRight);
    $this->writeText($target, $x, $y, $index, $item, $textLimit, $fg, $bg);
    if ($right !== '' && $reservedRight > 0) {
      $rightX = $item->rightAlign() === 'left' ? $content->right() - $reservedRight : $content->right() - mb_strlen($right);
      if ($rightX >= $content->x && $rightX < $content->right()) {
        $target->write($rightX, $y, mb_substr($right, 0, max(0, $content->right() - $rightX)), $this->theme->markerFg, $bg);
      }
    }
  }

  protected function writeText(RenderTarget $target, int $x, int $y, int $index, ListItem $item, int $limit, Color|string|int $fg, Color|string|int $bg): void {
    if ($limit <= 0) {
      return;
    }
    $text = $this->truncate($item->text(), $limit, $item->truncateMarker());
    $match = $this->matches[$index] ?? null;
    if ($match === null) {
      $target->write($x, $y, $text, $fg, $bg);
      return;
    }
    $start = min((int)$match['start'], mb_strlen($text));
    $length = min((int)$match['length'], max(0, mb_strlen($text) - $start));
    $target->write($x, $y, mb_substr($text, 0, $start), $fg, $bg);
    if ($length > 0) {
      $target->write($x + $start, $y, mb_substr($text, $start, $length), '#000000', '#ffff00');
    }
    $target->write($x + $start + $length, $y, mb_substr($text, $start + $length), $fg, $bg);
  }

  protected function normalizeItem(ListItem|array|string $item): ListItem {
    return $item instanceof ListItem ? $item : new ListItem($item);
  }

  protected function refreshItems(bool $invalidate = true): void {
    $this->clampCursor();
    $this->applySearch();
    $this->syncScroll();
    $this->refreshSelectionOrder();
    if ($invalidate) {
      $this->invalidateRender();
    }
  }

  protected function clampCursor(): void {
    if (empty($this->items)) {
      $this->cursor = 0;
      $this->scroll = 0;
      return;
    }
    $this->cursor = max(0, min(count($this->items) - 1, $this->cursor));
    $visible = $this->visibleIndexes();
    if ($visible !== [] && !in_array($this->cursor, $visible, true)) {
      $this->cursor = $visible[0];
    }
  }

  protected function handleText(string $text): bool {
    if (!$this->searchable || $text === '') {
      return false;
    }
    $candidate = $this->search . $text;
    if (!$this->hasSearchMatch($candidate)) {
      return true;
    }
    $this->search = $candidate;
    $this->applySearch();
    $this->syncScroll();
    $this->invalidateRender();
    return true;
  }

  protected function handleBackspace(): bool {
    if (!$this->searchable || $this->search === '') {
      return false;
    }
    $this->search = mb_substr($this->search, 0, -1);
    $this->applySearch();
    return true;
  }

  protected function handleDelete(): bool {
    if (!$this->searchable || $this->search === '') {
      return false;
    }
    $this->search = '';
    $this->applySearch();
    return true;
  }

  protected function applySearch(): void {
    $this->matches = [];
    if ($this->search === '') {
      $this->clampCursor();
      return;
    }
    $first = null;
    foreach ($this->items as $i => $item) {
      if (!$item->filterable()) {
        continue;
      }
      $match = $this->matchLabel($item->text(), $this->search);
      if ($match === null) {
        continue;
      }
      $this->matches[$i] = $match;
      $first ??= $i;
    }
    if ($first !== null) {
      $this->cursor = $first;
    }
  }

  protected function hasSearchMatch(string $search): bool {
    foreach ($this->items as $item) {
      if ($item->filterable() && $this->matchLabel($item->text(), $search) !== null) {
        return true;
      }
    }
    return false;
  }

  protected function matchLabel(string $label, string $search): ?array {
    if ($search === '') {
      return null;
    }
    $labelLower = mb_strtolower($label);
    $searchLower = mb_strtolower($search);
    if (!str_starts_with($labelLower, $searchLower)) {
      return null;
    }
    return ['start' => 0, 'length' => mb_strlen($search)];
  }

  protected function visibleIndexes(): array {
    if (!$this->filterable || $this->search === '') {
      return array_keys($this->items);
    }
    return array_values(array_keys($this->matches));
  }

  protected function visibleRows(): int {
    return max(1, min($this->frame->height > 0 ? $this->frame->height : $this->visibleItemCount(), $this->visibleItemCount()));
  }

  protected function visibleItemCount(): int {
    return max(1, count($this->visibleIndexes()));
  }

  protected function moveVisibleCursor(int $delta): void {
    $visible = $this->visibleIndexes();
    if ($visible === []) {
      $this->cursor = 0;
      return;
    }
    $position = array_search($this->cursor, $visible, true);
    if ($position === false) {
      $position = 0;
    }
    $this->moveCursorTo($visible[max(0, min(count($visible) - 1, $position + $delta))]);
  }

  protected function moveActiveItem(int $delta): bool {
    if (!$this->reorderable || count($this->items) < 2) {
      return false;
    }
    $from = $this->cursor;
    $to = max(0, min(count($this->items) - 1, $from + $delta));
    if ($to === $from) {
      return true;
    }
    $item = $this->items[$from];
    array_splice($this->items, $from, 1);
    array_splice($this->items, $to, 0, [$item]);
    $this->cursor = $to;
    $this->refreshItems();
    $this->syncScroll();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
    return true;
  }

  protected function moveCursorTo(int $cursor): void {
    $old = $this->cursor;
    $this->cursor = max(0, min(max(0, count($this->items) - 1), $cursor));
    if ($this->cursor !== $old && $this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function syncScroll(): void {
    $indexes = $this->visibleIndexes();
    $position = array_search($this->cursor, $indexes, true);
    $position = $position === false ? 0 : $position;
    $rows = $this->visibleRows();
    if ($position < $this->scroll) {
      $this->scroll = $position;
    }
    if ($position >= $this->scroll + $rows) {
      $this->scroll = $position - $rows + 1;
    }
    $this->scroll = max(0, min($this->scroll, max(0, count($indexes) - $rows)));
  }

  protected function selectActiveItem(): bool {
    $item = $this->activeItem();
    if ($item === null || !$item->enabled()) {
      return false;
    }
    $selectable = $item->selectable();
    if ($selectable === true) {
      $this->selectItem($item);
    } else if ($selectable !== false) {
      if ($item->selected()) {
        $item->deselect();
      } else {
        foreach ($this->items as $candidate) {
          if ($candidate === $item) {
            $candidate->select();
          } else if ($candidate->selectable() === $selectable) {
            $candidate->deselect();
          }
        }
      }
    } else if ($this->onSelect === null) {
      return false;
    }
    if ($this->onSelect !== null) {
      ($this->onSelect)($item);
    }
    $this->invalidateRender();
    return true;
  }

  protected function selectItem(ListItem $item): void {
    if (!$this->selectionOrder) {
      if ($item->selected()) {
        $item->deselect();
      } else {
        $item->select();
      }
      return;
    }
    $id = spl_object_id($item);
    if ($item->selected()) {
      $this->selectedOrder = array_values(array_filter($this->selectedOrder, fn($selectedId) => $selectedId !== $id));
      $item->deselect();
    } else {
      $this->selectedOrder[] = $id;
      $item->select(count($this->selectedOrder));
    }
    $this->refreshSelectionOrder();
  }

  protected function rememberSelected(ListItem $item): void {
    if ($item->selectable() === true) {
      $this->selectedOrder[] = spl_object_id($item);
    }
  }

  protected function refreshSelectionOrder(): void {
    if (!$this->selectionOrder) {
      return;
    }
    foreach ($this->items as $item) {
      if ($item->selectable() === true && $item->selected()) {
        $order = array_search(spl_object_id($item), $this->selectedOrder, true);
        if ($order !== false) {
          $item->markSelected($order + 1);
        }
      }
    }
  }

  protected function leftWidth(array $indexes): int {
    $width = 0;
    foreach ($indexes as $index) {
      $item = $this->items[$index];
      $width = max($width, $item->leftReserve(), $item->selectable() === false ? 0 : 2, mb_strlen($item->left()));
    }
    return $width;
  }

  protected function rightWidth(array $indexes): int {
    $width = 0;
    foreach ($indexes as $index) {
      $item = $this->items[$index];
      $right = mb_strlen($item->right());
      $width = max($width, $right === 0 ? 0 : $right + 1, $item->rightReserve());
    }
    return $width;
  }

  protected function truncate(string $text, int $limit, string $marker): string {
    if (mb_strlen($text) <= $limit) {
      return $text;
    }
    if ($marker === '') {
      return mb_substr($text, 0, $limit);
    }
    $markerLength = mb_strlen($marker);
    if ($limit <= $markerLength) {
      return mb_substr($marker, 0, $limit);
    }
    return mb_substr($text, 0, $limit - $markerLength) . $marker;
  }

  protected function paintScrollbar(RenderTarget $target): void {
    if (count($this->visibleIndexes()) <= $this->visibleRows()) {
      return;
    }
    Scrollbar::paintBar(
      $target,
      new Rect($this->frame->right() - 1, $this->frame->y, 1, $this->frame->height),
      $this->scroll,
      $this->visibleRows(),
      count($this->visibleIndexes()),
      'vertical',
      $this->theme->markerFg
    );
  }

  protected function fieldFg(): Color {
    return Color::from($this->fieldFg ?? $this->theme->fg);
  }

  protected function fieldBg(): Color {
    $color = Color::from($this->fieldBg ?? $this->theme->bg);
    if ($this->focused) {
      return $this->brightenColor($color);
    }
    return $color;
  }

  protected function brightenColor(Color $color): Color {
    return new Color(
      min(255, (int)round($color->r * 1.2)),
      min(255, (int)round($color->g * 1.2)),
      min(255, (int)round($color->b * 1.2)),
      $color->a
    );
  }

  protected function contentRect(): Rect {
    $scrollbar = count($this->visibleIndexes()) > $this->visibleRows() ? 1 : 0;
    $x = min($this->frame->right(), $this->frame->x + $this->paddingX);
    $right = max($x, $this->frame->right() - $this->paddingX - $scrollbar);
    return new Rect($x, $this->frame->y, max(0, $right - $x), $this->frame->height);
  }

}
