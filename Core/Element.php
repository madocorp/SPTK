<?php

namespace SPTK\Core;

/**
 * Provides the base tree, layout, input, and rendering contract for elements.
 */
abstract class Element {

  protected Rect $frame;
  protected ?Element $parent = null;
  protected array $children = [];
  protected Theme $theme;
  protected ?ElementContext $context = null;
  protected bool $focusable = false;
  protected bool $focused = false;
  protected bool $absolute = false;
  protected string $gridAlignment = 'center';
  protected int $preferredRows = 1;
  protected int $preferredColumns = 0;

  public function __construct(protected string $name = '') {
    $this->frame = new Rect(0, 0, 0, 0);
    $this->theme = new Theme();
  }

  public function name(): string {
    return $this->name;
  }

  public function setTheme(Theme $theme): static {
    $this->theme = $theme;
    foreach ($this->children as $child) {
      $child->setTheme($theme);
    }
    return $this;
  }

  public function setContext(?ElementContext $context): static {
    $this->context = $context;
    foreach ($this->children as $child) {
      $child->setContext($context);
    }
    return $this;
  }

  public function add(Element $child): static {
    $child->parent = $this;
    $child->setTheme($this->theme);
    $child->setContext($this->context);
    $this->children[] = $child;
    $this->invalidateLayout();
    $this->invalidateFocus();
    return $this;
  }

  public function children(): array {
    return $this->children;
  }

  public function parent(): ?Element {
    return $this->parent;
  }

  public function context(): ?ElementContext {
    return $this->context;
  }

  public function theme(): Theme {
    return $this->theme;
  }

  public function surfaceBackground(): Color|string|int|null {
    return $this->theme->bg;
  }

  public function remove(Element $child): bool {
    foreach ($this->children as $i => $candidate) {
      if ($candidate !== $child) {
        continue;
      }
      array_splice($this->children, $i, 1);
      $child->parent = null;
      $child->setContext(null);
      $this->invalidateLayout();
      $this->invalidateFocus();
      return true;
    }
    return false;
  }

  public function root(): Element {
    $element = $this;
    while ($element->parent !== null) {
      $element = $element->parent;
    }
    return $element;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function frame(): Rect {
    return $this->frame;
  }

  public function setGridAlignment(string $alignment): static {
    $this->gridAlignment = $alignment;
    $this->invalidateRender();
    return $this;
  }

  public function gridAlignment(): string {
    return $this->gridAlignment;
  }

  public function setPreferredRows(int $rows): static {
    $this->preferredRows = max(1, $rows);
    $this->invalidateLayout();
    return $this;
  }

  public function preferredRows(): int {
    return $this->preferredRows;
  }

  public function preferredRowsForColumns(int $columns): int {
    return $this->preferredRows();
  }

  public function setPreferredColumns(int $columns): static {
    $this->preferredColumns = max(0, $columns);
    $this->invalidateLayout();
    return $this;
  }

  public function preferredColumns(): int {
    return $this->preferredColumns;
  }

  public function isAbsolute(): bool {
    return $this->absolute;
  }

  public function isFocusable(): bool {
    return $this->focusable;
  }

  public function isTabStop(): bool {
    return $this->isFocusable();
  }

  public function focusScope(): string {
    return 'transparent';
  }

  public function focusProxy(): ?Element {
    return $this->firstFocusableDescendant();
  }

  public function rememberFocus(Element $element): void {
    ;
  }

  public function firstFocusableDescendant(): ?Element {
    foreach ($this->children as $child) {
      if ($child->isFocusable()) {
        return $child;
      }
      $descendant = $child->firstFocusableDescendant();
      if ($descendant !== null) {
        return $descendant;
      }
    }
    return null;
  }

  public function contains(Element $target): bool {
    for ($element = $target; $element !== null; $element = $element->parent()) {
      if ($element === $this) {
        return true;
      }
    }
    return false;
  }

  public function setFocused(bool $focused): void {
    if ($this->focused !== $focused) {
      $this->invalidateRender();
    }
    $this->focused = $focused;
  }

  public function isFocused(): bool {
    return $this->focused;
  }

  public function handle(InputEvent $event): bool {
    return false;
  }

  public function shortcut(InputEvent $event): bool {
    if ($this->handleShortcut($event)) {
      return true;
    }
    foreach ($this->children as $child) {
      if ($child->shortcut($event)) {
        return true;
      }
    }
    return false;
  }

  public function layout(): void {
    foreach ($this->children as $child) {
      if ($child->isAbsolute()) {
        continue;
      }
      $child->setFrame($this->frame);
    }
  }

  protected function invalidateRender(): void {
    $this->context?->invalidateRender();
  }

  protected function invalidateLayout(): void {
    $this->context?->invalidateLayout();
  }

  protected function invalidateFocus(): void {
    $this->context?->invalidateFocus();
  }

  public function requestFocus(): void {
    $this->context?->requestFocus($this);
  }

  protected function handleShortcut(InputEvent $event): bool {
    return false;
  }

  public function render(RenderTarget $target): void {
    $target->pushClip($this->frame);
    $this->paint($target);
    foreach ($this->children as $child) {
      $child->render($target);
    }
    $target->popClip();
  }

  abstract protected function paint(RenderTarget $target): void;

}
