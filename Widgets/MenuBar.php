<?php

namespace SPTK\Widgets;

use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Element;

/**
 * Renders top-level menu labels across a single grid row.
 */
class MenuBar extends Element {

  protected int $cursor = 0;
  protected ?MenuPopup $popup = null;
  protected ?Element $returnFocus = null;
  protected bool $browsing = false;

  public function __construct(string $name = '', protected array $items = []) {
    parent::__construct($name);
    $this->focusable = true;
    $this->gridAlignment = 'top-left';
  }

  public function isTabStop(): bool {
    return false;
  }

  public function cursor(): int {
    return $this->cursor;
  }

  public function items(): array {
    return $this->items;
  }

  public function addItem(MenuItem|array|string $item, ?int $index = null): void {
    $this->insertItem($this->items, $item, $index);
    $this->clampCursor();
    $this->invalidateRender();
  }

  public function addSubmenu(array $path, array $items = []): bool {
    $changed = $this->ensureSubmenu($this->items, $path, $items);
    if ($changed) {
      $this->closePopup();
      $this->invalidateRender();
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
      $this->closePopup();
      $this->invalidateRender();
    }
    return $changed;
  }

  public function openItem(int $index): bool {
    if (!isset($this->items[$index])) {
      return false;
    }
    $this->cursor = $index;
    if (!$this->openSelectedSubmenu()) {
      return false;
    }
    $this->invalidateRender();
    return true;
  }

  public function refreshOpenPopup(bool $closeChildPopup = false): bool {
    if ($this->popup === null || !isset($this->items[$this->cursor])) {
      return false;
    }
    $item = $this->items[$this->cursor];
    $this->runItemOnOpen($item);
    if (!$this->itemHasItems($item)) {
      $this->closePopup();
      return false;
    }
    $this->popup->setItems($this->itemItems($item), $closeChildPopup);
    $this->invalidateRender();
    return true;
  }

  public function handle(InputEvent $event): bool {
    if ($event->type === 'text' && $this->browsing) {
      return true;
    }
    if ($event->type !== 'key' || empty($this->items)) {
      return false;
    }
    $moved = false;
    if (InputAction::left($event, 'menu')) {
      $this->cursor = ($this->cursor - 1 + count($this->items)) % count($this->items);
      $moved = true;
    } else if (InputAction::right($event, 'menu')) {
      $this->cursor = ($this->cursor + 1) % count($this->items);
      $moved = true;
    } else if (InputAction::home($event, 'menu')) {
      $this->cursor = 0;
      $moved = true;
    } else if (InputAction::end($event, 'menu')) {
      $this->cursor = count($this->items) - 1;
      $moved = true;
    } else if (InputAction::down($event, 'menu')) {
      if (!$this->openSelectedSubmenu()) {
        return false;
      }
    } else if (InputAction::activate($event, 'menu')) {
      $this->activate(true);
    } else if (InputAction::cancel($event, 'menu')) {
      $this->closePopup(true, true);
    } else {
      return false;
    }
    if ($moved && $this->browsing) {
      $this->activate(false);
    }
    $this->invalidateRender();
    return true;
  }

  protected function handleShortcut(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    if ($this->browsing && $this->popup === null && $this->isBrowseAction($event)) {
      return $this->handle($event);
    }
    if (!preg_match('/^F([1-9]|1[0-2])$/', $event->key, $match)) {
      return false;
    }
    $index = (int)$match[1] - 1;
    if (!isset($this->items[$index])) {
      return false;
    }
    $this->cursor = $index;
    $this->activate(true);
    $this->invalidateRender();
    return true;
  }

  protected function isBrowseAction(InputEvent $event): bool {
    return InputAction::left($event, 'menu') ||
      InputAction::right($event, 'menu') ||
      InputAction::home($event, 'menu') ||
      InputAction::end($event, 'menu') ||
      InputAction::down($event, 'menu') ||
      InputAction::activate($event, 'menu') ||
      InputAction::cancel($event, 'menu');
  }

  protected function paint(RenderTarget $target): void {
    $target->fillToWindowEdge($this->frame, ' ', $this->theme->inverseFg, $this->theme->inverseBg);
    $x = $this->frame->x + 1;
    foreach ($this->items as $i => $item) {
      $hotkey = (string)($i + 1);
      $label = $hotkey . ' ' . $this->itemLabel($item);
      $text = ' ' . $label . ' ';
      if ($x + mb_strlen($text) > $this->frame->right()) {
        break;
      }
      $active = $i === $this->cursor && ($this->focused || $this->popup !== null || $this->browsing);
      $bg = $active ? $this->theme->menuActiveBg : $this->theme->inverseBg;
      $target->write($x, $this->frame->y, $text, $this->theme->inverseFg, $bg, $active ? ['active'] : []);
      $target->write($x + 1, $this->frame->y, $hotkey, $this->theme->hotkey, $bg, $active ? ['active'] : []);
      $x += mb_strlen($text) + 1;
    }
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

  protected function itemAction(mixed $item): ?callable {
    if ($item instanceof MenuItem) {
      return $item->action();
    }
    if (is_array($item) && isset($item['action']) && is_callable($item['action'])) {
      return $item['action'];
    }
    return null;
  }

  protected function itemOnOpen(mixed $item): ?callable {
    if ($item instanceof MenuItem) {
      return $item->onOpen();
    }
    if (is_array($item) && isset($item['onOpen']) && is_callable($item['onOpen'])) {
      return $item['onOpen'];
    }
    return null;
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

  protected function clampCursor(): void {
    if (empty($this->items)) {
      $this->cursor = 0;
      return;
    }
    $this->cursor = min($this->cursor, count($this->items) - 1);
  }

  protected function activate(bool $runAction): void {
    $item = $this->items[$this->cursor] ?? null;
    $this->runItemOnOpen($item);
    if ($this->itemHasItems($item)) {
      $this->openPopup($item);
      return;
    }
    if ($runAction) {
      $this->closePopup(true, true);
    } else {
      $this->closePopup();
      $this->browsing = true;
      $this->requestFocus();
    }
    $action = $this->itemAction($item);
    if ($runAction && $action !== null) {
      if ($this->context !== null) {
        $this->context->deferAction(fn() => $action($item, $this));
      } else {
        $action($item, $this);
      }
    }
  }

  protected function openSelectedSubmenu(): bool {
    $item = $this->items[$this->cursor] ?? null;
    $this->runItemOnOpen($item);
    if (!$this->itemHasItems($item)) {
      return false;
    }
    $this->openPopup($item);
    return true;
  }

  public function activatePreviousTopItem(): void {
    if (empty($this->items)) {
      return;
    }
    $this->cursor = ($this->cursor - 1 + count($this->items)) % count($this->items);
    $this->activate(false);
    $this->invalidateRender();
  }

  public function activateNextTopItem(): void {
    if (empty($this->items)) {
      return;
    }
    $this->cursor = ($this->cursor + 1) % count($this->items);
    $this->activate(false);
    $this->invalidateRender();
  }

  public function closePopup(bool $exitBrowsing = false, bool $restoreFocus = false): void {
    if ($this->popup === null) {
      if ($exitBrowsing) {
        $this->browsing = false;
      }
      if ($restoreFocus) {
        $this->restoreFocusAfterPopup();
      }
      return;
    }
    $this->popup->closeDescendants();
    $this->root()->remove($this->popup);
    $this->popup = null;
    if ($exitBrowsing) {
      $this->browsing = false;
    }
    if ($restoreFocus) {
      $this->restoreFocusAfterPopup();
    }
    $this->invalidateRender();
  }

  protected function openPopup(mixed $item): void {
    if ($this->popup === null) {
      $this->returnFocus = $this->context?->lastNonPopupFocus();
    }
    $this->closePopup();
    $layout = $this->itemLayout($item);
    $layout['labelWidth'] = max((int)($layout['labelWidth'] ?? 0), mb_strlen($this->itemLabel($item)));
    $popup = new MenuPopup($this->name . '-popup', $this->itemItems($item), $layout, $this);
    $rootFrame = $this->root()->frame();
    $height = min($popup->preferredHeight(), $this->maxPopupHeight($rootFrame, $layout));
    $width = $popup->framedWidthForHeight($height);
    $x = min($this->itemX($this->cursor), max(0, $rootFrame->right() - $width));
    $y = min($this->frame->y + 1, max(0, $rootFrame->bottom() - $height));
    $popup->setFrame(new Rect($x, $y, $width, $height));
    $this->root()->add($popup);
    $this->popup = $popup;
    $this->browsing = true;
    $popup->focusOnOpen();
  }

  protected function runItemOnOpen(mixed $item): void {
    $onOpen = $this->itemOnOpen($item);
    if ($onOpen !== null) {
      $onOpen($item, $this);
    }
  }

  protected function restoreFocusAfterPopup(): void {
    if ($this->returnFocus !== null && $this->root()->contains($this->returnFocus)) {
      $this->returnFocus->requestFocus();
    }
    $this->returnFocus = null;
  }

  protected function itemX(int $index): int {
    $x = $this->frame->x + 1;
    foreach ($this->items as $i => $item) {
      if ($i === $index) {
        return $x;
      }
      $text = ' ' . ($i + 1) . ' ' . $this->itemLabel($item) . ' ';
      $x += mb_strlen($text) + 1;
    }
    return $this->frame->x + 1;
  }

  protected function maxPopupHeight(Rect $rootFrame, array $layout): int {
    $ratio = (float)($layout['maxHeightRatio'] ?? 0.7);
    $maxRows = (int)($layout['maxHeightRows'] ?? 0);
    $height = max(1, (int)floor($rootFrame->height * max(0.1, min(1.0, $ratio))));
    if ($maxRows > 0) {
      $height = min($height, $maxRows);
    }
    return min($rootFrame->height, $height);
  }

}
