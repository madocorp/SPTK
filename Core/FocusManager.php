<?php

namespace SPTK\Core;

/**
 * Tracks focusable elements and routes keyboard/text input to the active element.
 */
class FocusManager {

  protected array $elements = [];
  protected int $index = -1;
  protected ?Element $focusedElement = null;

  public function __construct(protected Element $root) {
    $this->rebuild();
  }

  public function current(): ?Element {
    return $this->focusedElement;
  }

  public function next(): ?Element {
    if (empty($this->elements)) {
      return null;
    }
    return $this->focus(($this->index + 1) % count($this->elements));
  }

  public function previous(): ?Element {
    if (empty($this->elements)) {
      return null;
    }
    return $this->focus(($this->index - 1 + count($this->elements)) % count($this->elements));
  }

  public function dispatch(InputEvent $event): bool {
    $focusRoot = $this->focusRoot();
    if ($event->type === 'key') {
      $shortcut = $focusRoot->shortcut($event);
      if ($shortcut) {
        $this->flushPostEvent();
        return true;
      }
    }
    if ($event->type === 'key' && $event->key === 'Tab') {
      $current = $this->current();
      if ($current !== null && $current->handle($event)) {
        $this->flushPostEvent();
        return true;
      }
      if (($event->modifiers['shift'] ?? false) === true) {
        $this->previous();
      } else {
        $this->next();
      }
      return true;
    }
    $current = $this->current();
    $handled = $current !== null && $current->handle($event);
    if ($handled) {
      $this->flushPostEvent();
      return true;
    }
    if ($this->dialogReturnFallback($focusRoot, $event)) {
      $this->next();
      return true;
    }
    return false;
  }

  public function rebuild(?Element $preferred = null): void {
    $current = $this->current();
    if ($current !== null) {
      $current->setFocused(false);
    }
    $this->elements = [];
    $this->index = -1;
    if ($preferred !== null) {
      $this->rememberWorkspaceFocus($preferred);
    }
    $focusRoot = $this->focusRoot();
    $this->collect($focusRoot);
    if ($preferred !== null && $preferred->isFocusable() && $focusRoot->contains($preferred)) {
      $preferredIndex = $this->indexOf($preferred);
      $this->focusElement($preferred, $preferredIndex);
      return;
    }
    if (empty($this->elements)) {
      $this->focusedElement = null;
      $this->root->context()?->setCurrentFocus(null);
      return;
    }
    $index = 0;
    if ($preferred !== null) {
      $index = $this->indexOf($preferred) ?? 0;
    } else if ($current !== null) {
      $index = $this->indexOf($current) ?? 0;
    }
    $this->focus($index);
  }

  protected function focus(int $index): Element {
    return $this->focusElement($this->elements[$index], $index);
  }

  protected function focusElement(Element $element, ?int $index = null): Element {
    if ($this->focusedElement !== null && $this->focusedElement !== $element) {
      $this->focusedElement->setFocused(false);
    }
    if ($index !== null) {
      $this->index = $index;
    }
    $this->focusedElement = $element;
    $element->setFocused(true);
    $this->root->context()?->setCurrentFocus($element);
    $this->rememberWorkspaceFocus($element);
    return $element;
  }

  protected function collect(Element $element): void {
    if ($element->focusScope() === 'modal') {
      $proxy = $element->focusProxy();
      if ($proxy !== null) {
        $this->collect($proxy);
      }
      return;
    }
    if ($element->focusScope() === 'workspace') {
      $proxy = $element->focusProxy();
      if ($proxy !== null) {
        $this->elements[] = $proxy;
      }
      return;
    }
    if ($element->isTabStop()) {
      $this->elements[] = $element;
    }
    foreach ($element->children() as $child) {
      $this->collect($child);
    }
  }

  protected function focusRoot(): Element {
    return $this->modalFocusRoot($this->root) ?? $this->root;
  }

  protected function modalFocusRoot(Element $element): ?Element {
    $root = null;
    if ($element->focusScope() === 'modal') {
      $root = $element->focusProxy();
    }
    foreach ($element->children() as $child) {
      $childRoot = $this->modalFocusRoot($child);
      if ($childRoot !== null) {
        $root = $childRoot;
      }
    }
    return $root;
  }

  protected function rememberWorkspaceFocus(Element $element): void {
    for ($parent = $element->parent(); $parent !== null; $parent = $parent->parent()) {
      if ($parent->focusScope() === 'workspace') {
        $parent->rememberFocus($element);
        return;
      }
    }
  }

  protected function indexOf(Element $target): ?int {
    foreach ($this->elements as $i => $element) {
      if ($element === $target) {
        return $i;
      }
    }
    return null;
  }

  protected function flushPostEvent(): void {
    $context = $this->root->context();
    if ($context === null) {
      return;
    }
    if ($context->hasRequestedFocus()) {
      $this->rebuild($context->takeRequestedFocus());
    }
    $context->runDeferredActions();
  }

  protected function dialogReturnFallback(Element $focusRoot, InputEvent $event): bool {
    return $focusRoot->focusScope() === 'dialog'
      && $event->type === 'key'
      && InputAction::normalizedKey($event->key) === 'Enter'
      && empty($event->modifiers['ctrl'])
      && empty($event->modifiers['alt'])
      && empty($event->modifiers['shift']);
  }

}
