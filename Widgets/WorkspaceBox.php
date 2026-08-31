<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\RenderTarget;

/**
 * Groups a workspace region into one focus stop and restores its last focused child.
 */
class WorkspaceBox extends Element {

  protected ?Element $lastFocused = null;

  public function focusScope(): string {
    return 'workspace';
  }

  public function rememberFocus(Element $element): void {
    if ($element !== $this && $this->contains($element) && $element->isFocusable()) {
      $this->lastFocused = $element;
    }
  }

  public function focusProxy(): ?Element {
    if ($this->lastFocused !== null && $this->contains($this->lastFocused) && $this->lastFocused->isFocusable()) {
      return $this->lastFocused;
    }
    return $this->firstFocusableDescendant();
  }

  protected function paint(RenderTarget $target): void {
    ;
  }

}
