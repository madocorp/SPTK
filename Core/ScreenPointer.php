<?php

namespace SPTK\Core;

use SPTK\Layout\{LayoutLeaf, WindowGeometry};
use SPTK\Widgets\Button\Button;

/** Adds pointer activation while sharing the keyboard's selection rules. */
trait ScreenPointer {

  private MouseInput $mouseInput;

  /** Route mouse input after applying the same guards as keyboard input. */
  public function handleMouseEvent(mixed $event, WindowGeometry $geometry): bool {
    if (($this->inputGuard !== null && ($this->inputGuard)($event))
      || $this->statusBar?->handleBlockingInput($event) || $this->statusOverlay) {
      $this->mouseInput->reset();
      return true;
    }
    return $this->mouseInput->handle($event, $geometry);
  }

  /** Cancel a pending click after a window or screen change. */
  public function resetMouseInput(): void {
    $this->mouseInput->reset();
  }

  /** Select and activate a clicked tile, finishing another active tile first. */
  public function activatePointerLeaf(LayoutLeaf $leaf, mixed $event): bool {
    if (!$this->initialSelectionNotified) {
      $this->notifySelect();
      $this->initialSelectionNotified = true;
    }
    if ($this->activeLeaf() === $leaf) {
      return false;
    }
    $this->screenInput->cancelCharacterHotkey();
    $this->selectLeaf($leaf);
    return $this->activateSelected($event);
  }

  /** Run Return's group, button, or widget activation for either input device. */
  private function activateSelected(mixed $event): bool {
    $leaf = $this->selectedLeaf();
    if ($this->focus->enter()) {
      $leaf?->dispatchNotification('unselect');
      $this->notifySelect();
      return true;
    }
    if ($leaf?->instance() instanceof Button) {
      $leaf->dispatchNotification('activate');
      $leaf->instance()->press($event);
      return true;
    }
    if ($leaf === null || !$leaf->instance()->canActivate()) {
      return true;
    }
    $this->inputMode = true;
    $leaf->dispatchNotification('activate');
    return true;
  }

}
