<?php

namespace SPTK\Core;

/**
 * Collects passive invalidation flags for one element tree.
 */
class ElementContext {

  protected bool $layoutDirty = true;
  protected bool $focusDirty = true;
  protected bool $renderDirty = true;
  protected ?Element $requestedFocus = null;
  protected ?Element $currentFocus = null;
  protected ?Element $lastNonPopupFocus = null;
  protected array $deferredActions = [];

  public function invalidateLayout(): void {
    $this->layoutDirty = true;
    $this->renderDirty = true;
  }

  public function invalidateFocus(): void {
    $this->focusDirty = true;
    $this->renderDirty = true;
  }

  public function requestFocus(Element $element): void {
    $this->requestedFocus = $element;
    $this->invalidateFocus();
  }

  public function takeRequestedFocus(): ?Element {
    $element = $this->requestedFocus;
    $this->requestedFocus = null;
    return $element;
  }

  public function hasRequestedFocus(): bool {
    return $this->requestedFocus !== null;
  }

  public function deferAction(callable $action): void {
    $this->deferredActions[] = $action;
  }

  public function runDeferredActions(): void {
    while ($this->deferredActions !== []) {
      $action = array_shift($this->deferredActions);
      $action();
    }
  }

  public function setCurrentFocus(?Element $element): void {
    $this->currentFocus = $element;
    if ($element !== null && !$element->isAbsolute()) {
      $this->lastNonPopupFocus = $element;
    }
  }

  public function currentFocus(): ?Element {
    return $this->currentFocus;
  }

  public function lastNonPopupFocus(): ?Element {
    return $this->lastNonPopupFocus;
  }

  public function invalidateRender(): void {
    $this->renderDirty = true;
  }

  public function layoutDirty(): bool {
    return $this->layoutDirty;
  }

  public function focusDirty(): bool {
    return $this->focusDirty;
  }

  public function renderDirty(): bool {
    return $this->renderDirty;
  }

  public function clearLayout(): void {
    $this->layoutDirty = false;
  }

  public function clearFocus(): void {
    $this->focusDirty = false;
  }

  public function clearRender(): void {
    $this->renderDirty = false;
  }

}
