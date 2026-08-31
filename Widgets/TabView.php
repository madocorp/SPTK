<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\ElementContext;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Core\Theme;

/**
 * Shows a one-line tab strip and the active tab pane.
 */
class TabView extends Element {

  protected array $tabs = [];
  protected int $activeIndex = 0;
  protected Color|string|int $activeFg = '#ffffff';
  protected Color|string|int $activeBg = '#000000';
  protected Color|string|int $inactiveFg = '#cccccc';
  protected Color|string|int $inactiveBg = '#555555';
  protected Color|string|int $separator = '#000000';
  protected Color|string|int|null $fieldFg = null;
  protected Color|string|int|null $fieldBg = null;
  protected $onActiveIndexChange = null;

  public function __construct(string $name = '', array $tabs = []) {
    parent::__construct($name);
    $this->gridAlignment = 'top-left';
    foreach ($tabs as $tab) {
      if (is_array($tab) && isset($tab['label'], $tab['pane']) && $tab['pane'] instanceof Element) {
        $this->addTab((string)$tab['label'], $tab['pane']);
      }
    }
  }

  public function addTab(string $label, Element $pane): static {
    parent::add($pane);
    $this->syncPaneFieldColors($pane);
    $this->tabs[] = ['label' => $label, 'pane' => $pane];
    $this->activeIndex = max(0, min($this->activeIndex, count($this->tabs) - 1));
    $this->syncPreferredColumns();
    $this->invalidateLayout();
    $this->invalidateFocus();
    $this->invalidateRender();
    return $this;
  }

  public function setTheme(Theme $theme): static {
    $this->theme = $theme;
    foreach ($this->tabs as $tab) {
      $tab['pane']->setTheme($theme);
    }
    return $this;
  }

  public function setContext(?ElementContext $context): static {
    $this->context = $context;
    foreach ($this->tabs as $tab) {
      $tab['pane']->setContext($context);
    }
    return $this;
  }

  public function setFieldColors(Color|string|int|null $fg = null, Color|string|int|null $bg = null): static {
    $this->fieldFg = $fg;
    $this->fieldBg = $bg;
    foreach ($this->tabs as $tab) {
      $this->syncPaneFieldColors($tab['pane']);
    }
    $this->invalidateRender();
    return $this;
  }

  public function tabs(): array {
    return $this->tabs;
  }

  public function activeIndex(): int {
    return $this->activeIndex;
  }

  public function activePane(): ?Element {
    return $this->tabs[$this->activeIndex]['pane'] ?? null;
  }

  public function setOnActiveIndexChange(callable $onActiveIndexChange): static {
    $this->onActiveIndexChange = $onActiveIndexChange;
    return $this;
  }

  public function setActiveIndex(int $index): static {
    if ($this->tabs === []) {
      $this->activeIndex = 0;
      return $this;
    }
    $index = max(0, min($index, count($this->tabs) - 1));
    if ($this->activeIndex !== $index) {
      $this->activeIndex = $index;
      $this->layout();
      $this->invalidateFocus();
      $this->invalidateRender();
      $this->activePane()?->requestFocus();
      if ($this->onActiveIndexChange !== null) {
        ($this->onActiveIndexChange)($this->activeIndex, (string)($this->tabs[$this->activeIndex]['label'] ?? ''));
      }
    }
    return $this;
  }

  public function children(): array {
    $pane = $this->activePane();
    return $pane === null ? [] : [$pane];
  }

  public function focusProxy(): ?Element {
    $pane = $this->activePane();
    if ($pane === null) {
      return null;
    }
    return $pane->isFocusable() ? $pane : $pane->focusProxy();
  }

  public function firstFocusableDescendant(): ?Element {
    $pane = $this->activePane();
    if ($pane === null) {
      return null;
    }
    return $pane->isFocusable() ? $pane : $pane->firstFocusableDescendant();
  }

  public function shortcut(InputEvent $event): bool {
    if ($this->handleShortcut($event)) {
      return true;
    }
    return $this->activePane()?->shortcut($event) ?? false;
  }

  public function preferredRows(): int {
    if ($this->frame->width > 0) {
      return $this->preferredRowsForColumns($this->frame->width);
    }
    $rows = 2;
    foreach ($this->tabs as $tab) {
      $rows = max($rows, 2 + $tab['pane']->preferredRows());
    }
    return $rows;
  }

  public function preferredRowsForColumns(int $columns): int {
    $rows = 2;
    foreach ($this->tabs as $tab) {
      $rows = max($rows, 2 + $tab['pane']->preferredRowsForColumns($columns));
    }
    return $rows;
  }

  public function layout(): void {
    $pane = $this->activePane();
    foreach ($this->tabs as $tab) {
      $tab['pane']->setFrame(new Rect($this->frame->x, $this->frame->y + 2, 0, 0));
    }
    if ($pane !== null) {
      $pane->setFrame(new Rect($this->frame->x, $this->frame->y + 2, $this->frame->width, max(0, $this->frame->height - 2)));
    }
  }

  public function render(RenderTarget $target): void {
    $target->pushClip($this->frame);
    $this->paint($target);
    $this->activePane()?->render($target);
    $this->paintSeparator($target);
    $target->popClip();
  }

  protected function handleShortcut(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    if (!preg_match('/^F([1-9]|1[0-2])$/', $event->key, $match)) {
      return false;
    }
    $index = (int)$match[1] - 1;
    if (!isset($this->tabs[$index])) {
      return false;
    }
    $this->setActiveIndex($index);
    return true;
  }

  protected function paint(RenderTarget $target): void {
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    $target->fill(new Rect($this->frame->x, $this->frame->y, $this->frame->width, 1), ' ', $this->theme->fg, $this->theme->bg);
    if ($this->frame->height > 1) {
      $target->fill(new Rect($this->frame->x, $this->frame->y + 1, $this->frame->width, 1), ' ', $this->theme->fg, $this->theme->bg);
    }
    $x = $this->frame->x;
    foreach ($this->tabs as $i => $tab) {
      $text = ' F' . ($i + 1) . ' ' . $tab['label'] . ' ';
      if ($x >= $this->frame->right()) {
        break;
      }
      $text = mb_substr($text, 0, max(0, $this->frame->right() - $x));
      $active = $i === $this->activeIndex;
      $target->write(
        $x,
        $this->frame->y,
        $text,
        $active ? $this->activeFg : $this->inactiveFg,
        $active ? $this->activeBg : $this->inactiveBg,
        $active ? ['active'] : []
      );
      $x += mb_strlen($text);
      if ($x < $this->frame->right()) {
        $target->put($x, $this->frame->y, ' ', $this->theme->fg, $this->theme->bg);
        $x++;
      }
    }
  }

  protected function paintSeparator(RenderTarget $target): void {
    if ($this->frame->width <= 0 || $this->frame->height <= 1 || !$target instanceof SurfaceRenderTarget) {
      return;
    }
    $surface = $target->currentSurfacePixelRect();
    $x1 = $surface->x + $this->frame->x * $target->cellWidth();
    $x2 = $x1 + $this->frame->width * $target->cellWidth();
    $y = $surface->y + ($this->frame->y + 1) * $target->cellHeight();
    $target->drawPixelLine($x1, $y, $x2, $y, $this->separator, 2);
  }

  protected function syncPreferredColumns(): void {
    $columns = max(0, count($this->tabs) - 1);
    foreach ($this->tabs as $i => $tab) {
      $columns += mb_strlen(' F' . ($i + 1) . ' ' . $tab['label'] . ' ');
    }
    $this->setPreferredColumns($columns);
  }

  protected function syncPaneFieldColors(Element $element): void {
    if ($element instanceof TextEditor || $element instanceof ListView || $element instanceof TableView) {
      $element->setFieldColors($this->fieldFg, $this->fieldBg);
    }
    foreach ($element->children() as $child) {
      $this->syncPaneFieldColors($child);
    }
  }

}
