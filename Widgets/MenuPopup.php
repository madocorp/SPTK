<?php

namespace SPTK\Widgets;

use SPTK\Core\InputEvent;
use SPTK\Core\InputAction;
use SPTK\Core\Color;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;
use SPTK\Core\Element;

/**
 * Renders and handles a floating popup menu with aligned adornment columns.
 */
class MenuPopup extends Element {

  protected int $cursor = 0;
  protected int $scroll = 0;
  protected array $layout = [];
  protected ?MenuPopup $childPopup = null;
  protected string $search = '';
  protected bool $containsSearch = false;
  protected array $matches = [];

  public function __construct(
    string $name,
    protected array $items,
    protected array $layoutHints = [],
    protected ?MenuBar $menuBar = null,
    protected ?MenuPopup $parentPopup = null
  ) {
    parent::__construct($name);
    $this->focusable = true;
    $this->absolute = true;
    $this->gridAlignment = 'top-left';
    $this->layout = $this->measureLayout();
  }

  public function preferredWidth(): int {
    return $this->layout['width'];
  }

  public function preferredHeight(): int {
    return count($this->items);
  }

  public function cursor(): int {
    return $this->cursor;
  }

  public function framedWidthForHeight(int $height): int {
    return $this->preferredWidth() + ($height < $this->preferredHeight() ? 1 : 0);
  }

  public function handle(InputEvent $event): bool {
    if (!in_array($event->type, ['key', 'text'], true) || empty($this->items)) {
      return false;
    }
    $ctrl = !empty($event->modifiers['ctrl']);
    if ($this->handleSearchKey($event)) {
      // Search input updates cursor, filtering, and match highlights.
    } else if (InputAction::down($event, 'menu')) {
      $this->moveVisibleCursor(1);
      $this->syncScroll();
      $this->closeChildPopup();
    } else if (InputAction::up($event, 'menu')) {
      $this->moveVisibleCursor(-1);
      $this->syncScroll();
      $this->closeChildPopup();
    } else if (InputAction::pageDown($event, 'menu')) {
      $ctrl ? $this->moveToVisibleEdge('last') : $this->moveVisibleCursor(max(1, $this->visibleRows() - 1));
      $this->syncScroll();
      $this->closeChildPopup();
    } else if (InputAction::pageUp($event, 'menu')) {
      $ctrl ? $this->moveToVisibleEdge('first') : $this->moveVisibleCursor(-max(1, $this->visibleRows() - 1));
      $this->syncScroll();
      $this->closeChildPopup();
    } else if (InputAction::home($event, 'menu')) {
      $visible = $this->visibleIndexes();
      $this->cursor = $visible[0] ?? 0;
      $this->syncScroll();
      $this->closeChildPopup();
    } else if (InputAction::end($event, 'menu')) {
      $visible = $this->visibleIndexes();
      $this->cursor = $visible[count($visible) - 1] ?? 0;
      $this->syncScroll();
      $this->closeChildPopup();
    } else if ($this->menuBar !== null && $this->tab($event)) {
      if (($event->modifiers['shift'] ?? false) === true) {
        $this->menuBar?->activatePreviousTopItem();
      } else {
        $this->menuBar?->activateNextTopItem();
      }
    } else if (InputAction::left($event, 'menu')) {
      if ($this->parentPopup !== null) {
        $this->parentPopup->closeChildPopup(true);
      } else {
        $this->menuBar?->activatePreviousTopItem();
      }
    } else if (InputAction::right($event, 'menu')) {
      if (!$this->openChildPopup() && $this->parentPopup === null) {
        $this->menuBar?->activateNextTopItem();
      }
    } else if (InputAction::cancel($event, 'menu')) {
      if ($this->parentPopup !== null) {
        $this->parentPopup->closeChildPopup(true);
      } else {
        $this->menuBar?->closePopup(true, true);
      }
    } else if (InputAction::newline($event, 'menu')) {
      $this->activate(true);
    } else if ($event->type === 'key' && InputAction::normalizedKey($event->key) === 'Space') {
      $this->activate(false);
    } else {
      return false;
    }
    $this->invalidateRender();
    return true;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->menuPopupFg, $this->theme->menuPopupBg);
    $visible = $this->visibleRows();
    $indexes = $this->visibleIndexes();
    for ($rowOffset = 0; $rowOffset < $visible; $rowOffset++) {
      $i = $indexes[$this->scroll + $rowOffset] ?? null;
      if (!isset($this->items[$i])) {
        break;
      }
      $item = $this->items[$i];
      $row = $this->frame->y + $rowOffset;
      $active = ($this->focused || $this->childPopup !== null) && $i === $this->cursor;
      $bg = $active ? $this->theme->menuPopupActiveBg : $this->theme->menuPopupBg;
      $fg = $active ? $this->theme->menuPopupActiveFg : ($this->itemEnabled($item) ? $this->theme->menuPopupFg : $this->theme->muted);
      $target->fill(new Rect($this->frame->x, $row, $this->frame->width, 1), ' ', $fg, $bg);
      $x = $this->frame->x + 1;
      $left = $this->leftValue($item);
      $label = $this->itemLabel($item);
      $right = $this->rightValue($item);
      if ($this->layout['leftWidth'] > 0) {
        $target->write($x, $row, str_pad($left, $this->layout['leftWidth']), $this->theme->hotkey, $bg);
        $x += $this->layout['leftWidth'] + 1;
      }
      $this->writeLabel($target, $x, $row, $i, $label, $fg, $bg);
      $x += $this->layout['labelWidth'] + 1;
      if ($this->layout['rightWidth'] > 0) {
        $target->write($x, $row, str_pad($right, $this->layout['rightWidth'], ' ', STR_PAD_LEFT), $this->theme->hotkey, $bg);
      }
    }
    $this->paintSeparators($target);
    $this->paintScrollbar($target);
    $target->drawOuterRect($this->frame, $this->theme->border, 2);
  }

  protected function activate(bool $closeSelectable): void {
    $item = $this->items[$this->cursor] ?? null;
    if (!$this->itemEnabled($item)) {
      return;
    }
    if ($this->itemHasItems($item)) {
      $this->openChildPopup();
      return;
    }
    $action = $this->itemAction($item);
    $selectable = $this->itemSelectable($item);
    if ($action === null && $selectable === false) {
      return;
    }
    if ($selectable !== false) {
      $this->selectItem($this->cursor);
      $item = $this->items[$this->cursor] ?? $item;
    }
    $close = $this->itemCloseOnActivate($item) ?? $closeSelectable;
    if (!$close) {
      if ($action !== null) {
        $action($this, $this->cursor, $item);
      }
      $this->requestFocus();
    } else {
      $context = $this->context;
      $this->menuBar?->closePopup(true, true);
      $cursor = $this->cursor;
      if ($action !== null && $context !== null) {
        $context->deferAction(fn() => $action($this, $cursor, $item));
      } else if ($action !== null) {
        $action($this, $cursor, $item);
      }
    }
    $this->invalidateRender();
  }

  public function item(int $index): MenuItem|array|string|null {
    return $this->items[$index] ?? null;
  }

  public function items(): array {
    return $this->items;
  }

  public function setItems(array $items, bool $closeChildPopup = true): void {
    if ($closeChildPopup) {
      $this->closeChildPopup();
    }
    $this->items = $items;
    $this->refreshItems($closeChildPopup);
    if ($this->search !== '') {
      $this->applySearch();
    }
    if (!$closeChildPopup) {
      $this->refreshOpenChildPopup();
    }
  }

  public function addItem(MenuItem|array|string $item, ?int $index = null): void {
    $this->insertItem($this->items, $item, $index);
    $this->refreshItems();
  }

  public function addSubmenu(array $path, array $items = []): bool {
    $changed = $this->ensureSubmenu($this->items, $path, $items);
    if ($changed) {
      $this->refreshItems();
    }
    return $changed;
  }

  public function addSubItem(array $path, MenuItem|array|string $item, ?int $index = null): bool {
    if ($path === []) {
      $this->addItem($item, $index);
      return true;
    }
    $changed = $this->insertSubItem($this->items, $path, $item, $index);
    if ($changed) {
      $this->refreshItems();
    }
    return $changed;
  }

  public function updateItem(int $index, array $changes): void {
    if (!isset($this->items[$index])) {
      return;
    }
    if ($this->items[$index] instanceof MenuItem) {
      $this->items[$index]->update($changes);
    } else if (is_array($this->items[$index])) {
      $this->items[$index] = array_replace($this->items[$index], $changes);
    } else {
      $this->items[$index] = new MenuItem(array_replace(['label' => (string)$this->items[$index]], $changes));
    }
    $this->refreshItems();
  }

  public function focusOnOpen(): void {
    $this->focusCheckedItem();
    $this->syncScroll();
    $this->requestFocus();
  }

  public function closeDescendants(): void {
    $this->closeChildPopup();
  }

  public function closeChildPopup(bool $focusSelf = false): void {
    if ($this->childPopup === null) {
      if ($focusSelf) {
        $this->requestFocus();
      }
      return;
    }
    $this->childPopup->closeDescendants();
    $this->root()->remove($this->childPopup);
    $this->childPopup = null;
    if ($focusSelf) {
      $this->requestFocus();
    }
    $this->invalidateRender();
  }

  protected function openChildPopup(): bool {
    $item = $this->items[$this->cursor] ?? null;
    if (!$this->itemEnabled($item) || !$this->itemHasItems($item)) {
      return false;
    }
    $this->closeChildPopup();
    $layout = $this->itemLayout($item);
    $popup = new MenuPopup($this->name . '-popup', $this->itemItems($item), $layout, $this->menuBar, $this);
    $rootFrame = $this->root()->frame();
    $height = min($popup->preferredHeight(), $this->maxPopupHeight($rootFrame));
    $width = $popup->framedWidthForHeight($height);
    $x = min($this->frame->right(), max(0, $rootFrame->right() - $width));
    $y = min($this->frame->y + $this->cursor - $this->scroll, max(0, $rootFrame->bottom() - $height));
    $popup->setFrame(new Rect($x, $y, $width, $height));
    $this->root()->add($popup);
    $this->childPopup = $popup;
    $popup->focusOnOpen();
    $this->invalidateRender();
    return true;
  }

  protected function insertItem(array &$items, MenuItem|array|string $item, ?int $index = null): void {
    if ($index === null || $index < 0 || $index >= count($items)) {
      $items[] = $item;
      return;
    }
    array_splice($items, $index, 0, [$item]);
  }

  protected function ensureSubmenu(array &$items, array $path, array $submenuItems = []): bool {
    if ($path === []) {
      return false;
    }
    $index = array_shift($path);
    if (!is_int($index) || !isset($items[$index])) {
      return false;
    }
    if ($items[$index] instanceof MenuItem) {
      if ($path !== []) {
        return $this->ensureSubmenuItem($items[$index], $path, $submenuItems);
      }
      foreach ($submenuItems as $item) {
        $items[$index]->addItem($item);
      }
      return true;
    }
    if ($path !== []) {
      if (!is_array($items[$index]) || !isset($items[$index]['items']) || !is_array($items[$index]['items'])) {
        return false;
      }
      return $this->ensureSubmenu($items[$index]['items'], $path, $submenuItems);
    }
    if (!is_array($items[$index])) {
      $items[$index] = [
        'label' => (string)$items[$index],
        'items' => $submenuItems,
      ];
      return true;
    }
    if (isset($items[$index]['items']) && is_array($items[$index]['items'])) {
      return true;
    }
    $items[$index]['items'] = $submenuItems;
    return true;
  }

  protected function insertSubItem(array &$items, array $path, MenuItem|array|string $item, ?int $index = null): bool {
    $target = array_shift($path);
    if (!is_int($target) || !isset($items[$target])) {
      return false;
    }
    if ($items[$target] instanceof MenuItem) {
      return $this->insertSubItemObject($items[$target], $path, $item, $index);
    }
    if (!is_array($items[$target])) {
      $items[$target] = [
        'label' => (string)$items[$target],
        'items' => [],
      ];
    }
    if (!isset($items[$target]['items']) || !is_array($items[$target]['items'])) {
      $items[$target]['items'] = [];
    }
    if ($path !== []) {
      return $this->insertSubItem($items[$target]['items'], $path, $item, $index);
    }
    $this->insertItem($items[$target]['items'], $item, $index);
    return true;
  }

  protected function ensureSubmenuItem(MenuItem $owner, array $path, array $submenuItems = []): bool {
    $target = array_shift($path);
    if (!is_int($target) || $owner->item($target) === null) {
      return false;
    }
    $child = $owner->item($target);
    if ($path !== []) {
      return $this->ensureSubmenuItem($child, $path, $submenuItems);
    }
    foreach ($submenuItems as $item) {
      $child->addItem($item);
    }
    return true;
  }

  protected function insertSubItemObject(MenuItem $owner, array $path, MenuItem|array|string $item, ?int $index = null): bool {
    if ($path === []) {
      $owner->addItem($item, $index);
      return true;
    }
    $target = array_shift($path);
    if (!is_int($target) || $owner->item($target) === null) {
      return false;
    }
    return $this->insertSubItemObject($owner->item($target), $path, $item, $index);
  }

  protected function refreshItems(bool $closeChildPopup = true): void {
    if ($closeChildPopup) {
      $this->closeChildPopup();
    }
    $this->cursor = min($this->cursor, max(0, count($this->items) - 1));
    $this->syncScroll();
    $this->layout = $this->measureLayout();
    $height = min($this->frame->height > 0 ? $this->frame->height : $this->preferredHeight(), $this->preferredHeight());
    $this->setFrame(new Rect($this->frame->x, $this->frame->y, $this->framedWidthForHeight($height), $height));
    $this->invalidateRender();
  }

  protected function refreshOpenChildPopup(): void {
    if ($this->childPopup === null) {
      return;
    }
    $item = $this->items[$this->cursor] ?? null;
    if (!$this->itemEnabled($item) || !$this->itemHasItems($item)) {
      $this->closeChildPopup();
      return;
    }
    $this->childPopup->setItems($this->itemItems($item), false);
  }

  protected function handleSearchKey(InputEvent $event): bool {
    if (!$this->searchable() && !$this->filterable()) {
      return false;
    }
    if (InputAction::backspace($event, 'menu')) {
      if ($this->search === '' && !$this->containsSearch) {
        return false;
      }
      if ($this->search !== '') {
        $this->search = mb_substr($this->search, 0, -1);
      }
      if ($this->search === '') {
        $this->containsSearch = false;
      }
      $this->applySearch();
      return true;
    }
    if ($event->type === 'text' && $event->text !== '') {
      $text = $event->text;
      if ($this->search === '' && $text === '*') {
        $this->containsSearch = true;
      } else {
        $candidate = $this->search . $text;
        if (!$this->hasSearchMatch($candidate, $this->containsSearch)) {
          return true;
        }
        $this->search = $candidate;
      }
      $this->applySearch();
      return true;
    }
    return false;
  }

  protected function tab(InputEvent $event): bool {
    return $event->type === 'key' && InputAction::normalizedKey($event->key) === 'Tab';
  }

  protected function applySearch(): void {
    $this->matches = [];
    if ($this->search === '') {
      $this->cursor = min($this->cursor, max(0, count($this->items) - 1));
      $this->syncScroll();
      $this->invalidateRender();
      return;
    }
    $first = null;
    foreach ($this->items as $i => $item) {
      $match = $this->matchLabel($this->itemLabel($item));
      if ($match === null) {
        continue;
      }
      $this->matches[$i] = $match;
      $first ??= $i;
    }
    if ($first !== null) {
      $this->cursor = $first;
    }
    $this->syncScroll();
    $this->invalidateRender();
  }

  protected function searchable(): bool {
    return !empty($this->layoutHints['searchable']) || $this->filterable();
  }

  protected function filterable(): bool {
    return !empty($this->layoutHints['filterable']);
  }

  protected function visibleIndexes(): array {
    if (!$this->filterable() || $this->search === '') {
      return array_keys($this->items);
    }
    return array_values(array_keys($this->matches));
  }

  protected function filteredIndexes(): array {
    return $this->visibleIndexes();
  }

  protected function visibleItemCount(): int {
    return max(1, count($this->visibleIndexes()));
  }

  protected function moveVisibleCursor(int $delta): void {
    $visible = $this->visibleIndexes();
    if (empty($visible)) {
      $this->cursor = 0;
      return;
    }
    $position = array_search($this->cursor, $visible, true);
    if ($position === false) {
      $position = 0;
    }
    $position = max(0, min(count($visible) - 1, $position + $delta));
    $this->cursor = $visible[$position];
  }

  protected function moveToVisibleEdge(string $edge): void {
    $visible = $this->visibleIndexes();
    if (empty($visible)) {
      $this->cursor = 0;
      return;
    }
    $this->cursor = $edge === 'last' ? $visible[count($visible) - 1] : $visible[0];
  }

  protected function matchLabel(string $label): ?array {
    return $this->matchLabelFor($label, $this->search, $this->containsSearch);
  }

  protected function hasSearchMatch(string $search, bool $contains): bool {
    foreach ($this->items as $item) {
      if ($this->matchLabelFor($this->itemLabel($item), $search, $contains) !== null) {
        return true;
      }
    }
    return false;
  }

  protected function matchLabelFor(string $label, string $search, bool $contains): ?array {
    if ($search === '') {
      return null;
    }
    $labelLower = mb_strtolower($label);
    $searchLower = mb_strtolower($search);
    $pos = $contains ? mb_strpos($labelLower, $searchLower) : (str_starts_with($labelLower, $searchLower) ? 0 : false);
    if ($pos === false) {
      return null;
    }
    return ['start' => $pos, 'length' => mb_strlen($search)];
  }

  protected function writeLabel(RenderTarget $target, int $x, int $y, int $index, string $label, Color|string|int $fg, Color|string|int $bg): void {
    $padded = str_pad($label, $this->layout['labelWidth']);
    $match = $this->matches[$index] ?? null;
    if ($match === null) {
      $target->write($x, $y, $padded, $fg, $bg);
      return;
    }
    $start = min((int)$match['start'], mb_strlen($label));
    $length = min((int)$match['length'], max(0, mb_strlen($label) - $start));
    $target->write($x, $y, mb_substr($padded, 0, $start), $fg, $bg);
    if ($length > 0) {
      $target->write($x + $start, $y, mb_substr($padded, $start, $length), '#000000', '#ffff00');
    }
    $target->write($x + $start + $length, $y, mb_substr($padded, $start + $length), $fg, $bg);
  }

  protected function paintSeparators(RenderTarget $target): void {
    $indexes = $this->visibleIndexes();
    foreach ($indexes as $position => $i) {
      $item = $this->items[$i];
      if ($position < $this->scroll || $position >= $this->scroll + $this->visibleRows() - 1) {
        continue;
      }
      if ($this->itemSeparatorAfter($item) && $position < count($indexes) - 1) {
        $y = $this->frame->y + ($position - $this->scroll) + 1;
        $target->drawLine($this->frame->x, $y, $this->frame->right(), $y, $this->theme->hotkey, 2);
      }
    }
  }

  protected function paintScrollbar(RenderTarget $target): void {
    if (!$this->scrollable()) {
      return;
    }
    Scrollbar::paintBar(
      $target,
      new Rect($this->frame->right() - 1, $this->frame->y, 1, $this->frame->height),
      $this->scroll,
      $this->visibleRows(),
      $this->visibleItemCount(),
      'vertical',
      $this->theme->hotkey
    );
  }

  protected function syncScroll(): void {
    if ($this->filterable() && $this->search !== '') {
      $visible = $this->filteredIndexes();
      $position = array_search($this->cursor, $visible, true);
      $position = $position === false ? 0 : $position;
      $rows = $this->visibleRows();
      if ($position < $this->scroll) {
        $this->scroll = $position;
      }
      if ($position >= $this->scroll + $rows) {
        $this->scroll = $position - $rows + 1;
      }
      $this->scroll = max(0, min($this->scroll, max(0, count($visible) - $rows)));
      return;
    }
    $visible = $this->visibleRows();
    if ($this->cursor < $this->scroll) {
      $this->scroll = $this->cursor;
    }
    if ($this->cursor >= $this->scroll + $visible) {
      $this->scroll = $this->cursor - $visible + 1;
    }
    $this->scroll = max(0, min($this->scroll, max(0, count($this->items) - $visible)));
  }

  protected function visibleRows(): int {
    return max(1, min($this->frame->height > 0 ? $this->frame->height : $this->visibleItemCount(), $this->visibleItemCount()));
  }

  protected function scrollable(): bool {
    return $this->visibleItemCount() > $this->visibleRows();
  }

  protected function maxPopupHeight(Rect $rootFrame): int {
    $ratio = (float)($this->layoutHints['maxHeightRatio'] ?? 0.7);
    $maxRows = (int)($this->layoutHints['maxHeightRows'] ?? 0);
    $height = max(1, (int)floor($rootFrame->height * max(0.1, min(1.0, $ratio))));
    if ($maxRows > 0) {
      $height = min($height, $maxRows);
    }
    return min($rootFrame->height, $height);
  }

  protected function measureLayout(): array {
    $leftWidth = $this->hintWidth('left');
    $labelWidth = $this->hintWidth('label');
    $rightWidth = $this->hintWidth('right');
    foreach ($this->items as $item) {
      $leftWidth = max($leftWidth, mb_strlen($this->leftValue($item)));
      $labelWidth = max($labelWidth, mb_strlen($this->itemLabel($item)));
      $rightWidth = max($rightWidth, mb_strlen($this->rightValue($item)));
      foreach (['left', 'label', 'right'] as $column) {
        foreach ($this->itemValues($item, $column) as $value) {
          ${$column . 'Width'} = max(${$column . 'Width'}, mb_strlen((string)$value));
        }
        $width = $this->itemWidth($item, $column);
        if ($width !== null) {
          ${$column . 'Width'} = max(${$column . 'Width'}, $width);
        }
      }
    }
    return [
      'leftWidth' => $leftWidth,
      'labelWidth' => $labelWidth,
      'rightWidth' => $rightWidth,
      'width' => 2 + $leftWidth + ($leftWidth > 0 ? 1 : 0) + $labelWidth + ($rightWidth > 0 ? 1 + $rightWidth : 0),
    ];
  }

  protected function hintWidth(string $column): int {
    $width = (int)($this->layoutHints["{$column}Width"] ?? 0);
    foreach ($this->layoutHints["{$column}Values"] ?? [] as $value) {
      $width = max($width, mb_strlen((string)$value));
    }
    return $width;
  }

  protected function itemLabel(mixed $item): string {
    if ($item instanceof MenuItem) {
      return $item->label();
    }
    if (is_array($item)) {
      return (string)($item['label'] ?? '');
    }
    return (string)$item;
  }

  protected function itemHasItems(mixed $item): bool {
    if ($item instanceof MenuItem) {
      return $item->hasItems();
    }
    return is_array($item) && !empty($item['items']);
  }

  protected function itemItems(mixed $item): array {
    if ($item instanceof MenuItem) {
      return $item->items();
    }
    return is_array($item) && isset($item['items']) && is_array($item['items']) ? $item['items'] : [];
  }

  protected function itemLayout(mixed $item): array {
    if ($item instanceof MenuItem) {
      return $item->layout();
    }
    return is_array($item) && isset($item['layout']) && is_array($item['layout']) ? $item['layout'] : [];
  }

  protected function itemValues(mixed $item, string $column): array {
    if ($item instanceof MenuItem) {
      return $item->values($column);
    }
    $values = is_array($item) ? ($item["{$column}Values"] ?? []) : [];
    return is_array($values) ? $values : [];
  }

  protected function itemWidth(mixed $item, string $column): ?int {
    if ($item instanceof MenuItem) {
      return $item->width($column);
    }
    return is_array($item) && isset($item["{$column}Width"]) ? (int)$item["{$column}Width"] : null;
  }

  protected function itemAction(mixed $item): ?callable {
    if ($item instanceof MenuItem) {
      return $item->action();
    }
    if (is_array($item) && isset($item['action']) && is_callable($item['action'])) {
      return $item['action'];
    }
    return null;
  }

  protected function selectItem(int $index): void {
    $item = $this->items[$index] ?? null;
    $selectable = $this->itemSelectable($item);
    if ($selectable === false) {
      return;
    }
    if ($selectable === true) {
      $this->updateItem($index, ['checked' => !$this->itemChecked($item)]);
      return;
    }
    foreach ($this->items as $itemIndex => $candidate) {
      if ($this->itemSelectable($candidate) === $selectable) {
        $this->updateItem($itemIndex, ['checked' => $itemIndex === $index]);
      }
    }
  }

  protected function itemSelectable(mixed $item): bool|string {
    if ($item instanceof MenuItem) {
      return $item->selectable();
    }
    if (!is_array($item)) {
      return false;
    }
    $selectable = $item['selectable'] ?? false;
    return is_string($selectable) ? $selectable : (bool)$selectable;
  }

  protected function itemChecked(mixed $item): bool {
    if ($item instanceof MenuItem) {
      return $item->checked();
    }
    return is_array($item) && !empty($item['checked']);
  }

  protected function focusCheckedItem(): void {
    foreach ($this->items as $index => $item) {
      if ($this->itemChecked($item) && $this->itemEnabled($item)) {
        $this->cursor = $index;
        return;
      }
    }
  }

  protected function itemSeparatorAfter(mixed $item): bool {
    if ($item instanceof MenuItem) {
      return $item->separatorAfter();
    }
    return is_array($item) && !empty($item['separatorAfter']);
  }

  protected function itemCloseOnActivate(mixed $item): ?bool {
    if ($item instanceof MenuItem) {
      return $item->closeOnActivate();
    }
    return is_array($item) && array_key_exists('closeOnActivate', $item) ? $item['closeOnActivate'] !== false : null;
  }

  protected function leftValue(mixed $item): string {
    if ($item instanceof MenuItem) {
      return $item->left();
    }
    if (!is_array($item)) {
      return '';
    }
    if (isset($item['left'])) {
      return (string)$item['left'];
    }
    if (!empty($item['checked'])) {
      return ($item['selectable'] ?? false) === true ? 'X' : '*';
    }
    return '';
  }

  protected function rightValue(mixed $item): string {
    if ($item instanceof MenuItem) {
      return $item->right();
    }
    if (!is_array($item)) {
      return '';
    }
    if (isset($item['right'])) {
      return (string)$item['right'];
    }
    if (!empty($item['items'])) {
      return '>';
    }
    return '';
  }

  protected function itemEnabled(mixed $item): bool {
    if ($item instanceof MenuItem) {
      return $item->enabled();
    }
    return !is_array($item) || !array_key_exists('enabled', $item) || $item['enabled'] !== false;
  }

}
