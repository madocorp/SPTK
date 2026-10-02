<?php

namespace SPTK\Core;

use SPTK\Events\{KeyNormalizer, ScreenInput};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile, WindowGeometry};
use SPTK\Widgets\Button\Button;
use SPTK\Widgets\StatusBar\StatusBar;

/** Owns one screen's layout, widget selection, and screen-level input. */
final class Screen {

  private array $leaves;
  private FocusNavigation $focus;
  private bool $inputMode = false;
  private bool $initialSelectionNotified = false;
  private array $events;
  private ScreenInput $screenInput;
  private array $widgetsById = [];
  public ?StatusBar $statusBar = null;

  /** Create a screen and attach screen-level event subscriptions to its leaves. */
  public function __construct(public LayoutNode $layout, public Color $borderColor = new Color(71, 85, 104), array $events = [], public string $id = '', public string $title = '') {
    $this->events = $events;
    $this->indexWidgets();
    $this->refreshStatus();
  }

  /** Reindex a changed layout, retaining focus and activation when the selected widget survives. */
  public function setLayout(LayoutNode $layout): void {
    $selected = $this->focus->rootSelectedLeaf();
    if ($this->focus->depth() > 0) {
      $this->release();
    }
    $retained = null;
    foreach ($layout->leaves(true) as $leaf) {
      if ($leaf->instance() === $selected?->instance()) {
        $retained = $leaf;
        break;
      }
    }
    if ($retained === null) {
      $this->release();
      $this->initialSelectionNotified = false;
    }
    $this->layout = $layout;
    $this->indexWidgets();
    if ($retained !== null) {
      $this->focus->select($retained);
    }
    $this->refreshStatus();
  }

  /** Index widget IDs and register button hotkeys at screen scope. */
  private function indexWidgets(): void {
    $this->leaves = $this->layout->leaves();
    $this->focus = new FocusNavigation($this->layout);
    $this->screenInput = new ScreenInput($this->events);
    $this->widgetsById = [];
    $this->statusBar = null;
    $indexed = $this->leaves;
    foreach ($this->layout->leaves(true) as $leaf) {
      if (!in_array($leaf, $indexed, true)) {
        $indexed[] = $leaf;
      }
    }
    foreach ($indexed as $leaf) {
      $leaf->setScreenEvents($this->events);
      $widget = $leaf->instance();
      if ($widget instanceof StatusBar) {
        if ($this->statusBar !== null) {
          throw new \RuntimeException('Screen accepts only one StatusBar.');
        }
        $this->statusBar = $widget;
      }
      if ($widget->id() !== null) {
        if (isset($this->widgetsById[$widget->id()])) {
          throw new \RuntimeException("Duplicate widget id: {$widget->id()}");
        }
        $this->widgetsById[$widget->id()] = $widget;
      }
      if ($widget instanceof Button) {
        $this->screenInput->register($widget);
      }
    }
  }

  /** Find a widget by its XML identifier. */
  public function widget(string $id): ?Widget {
    return $this->widgetsById[$id] ?? null;
  }

  /** Return the leaf receiving input while a widget is activated. */
  public function activeLeaf(): ?LayoutLeaf {
    return $this->inputMode ? $this->selectedLeaf() : null;
  }

  /** Bind all buttons to their owning window. */
  public function setWindow(Window $window): void {
    foreach ($this->leaves as $leaf) {
      if ($leaf->instance() instanceof Button) {
        $leaf->instance()->setWindow($window);
      }
    }
  }

  /** Mark the current screen button and align selector focus when this screen is shown. */
  public function setCurrentScreenId(string $id): void {
    $selected = $this->selectedLeaf();
    $selectorFocused = $id === $this->id && $selected?->instance() instanceof Button && $selected->instance()->screenId() !== null;
    foreach ($this->leaves as $leaf) {
      $widget = $leaf->instance();
      if ($widget instanceof Button && $widget->screenId() !== null) {
        $widget->setActivated($widget->screenId() === $id);
        if ($selectorFocused && $widget->screenId() === $id) {
          $this->selectLeaf($leaf);
        }
      }
    }
  }

  /** Focus this screen's own selector button when its hotkey is pressed again. */
  public function focusScreenSelectorButton(): bool {
    foreach ($this->leaves as $leaf) {
      $widget = $leaf->instance();
      if ($widget instanceof Button && $widget->screenId() === $this->id) {
        $this->release();
        return $this->selectLeaf($leaf);
      }
    }
    return false;
  }

  /** Select a known leaf and send focus notifications after initial selection. */
  public function selectLeaf(LayoutLeaf $leaf): bool {
    if (!$this->focus->contains($leaf)) {
      throw new \InvalidArgumentException('Selected leaf does not belong to this navigation scope.');
    }
    $previous = $this->selectedLeaf();
    if ($this->inputMode && $previous !== $leaf) {
      $this->release();
    }
    if (!$this->focus->select($leaf)) {
      return false;
    }
    if ($this->initialSelectionNotified) {
      $previous?->dispatchNotification('unselect');
      $leaf->dispatchNotification('select');
    }
    $this->refreshStatus();
    return true;
  }

  /** Route input through the active widget, screen subscriptions, and screen controls. */
  public function handleEvent(mixed $event): bool {
    if ($this->statusBar?->handleConfirmation($event)) {
      return true;
    }
    $type = $this->screenInput->type($event);
    if ($type === null) {
      return false;
    }
    $deferred = $type === 'keyDown' && $this->screenInput->deferCharacterHotkey(
      $event, !$this->inputMode
    );
    $handled = $this->routeEvent($event, $type);
    if ($type === 'textInput' || $type === 'keyUp') {
      if ($this->inputMode) {
        $this->screenInput->cancelCharacterHotkey();
      } else {
        $handled = $this->screenInput->finishCharacterHotkey($event) || $handled;
      }
    }
    return $handled || $deferred;
  }

  /** Send one event to the active widget, screen actions, and tile navigation. */
  private function routeEvent(mixed $event, string $type): bool {
    if (!$this->initialSelectionNotified) {
      $this->selectedLeaf()?->dispatchNotification('select');
      $this->initialSelectionNotified = true;
    }
    $leaf = $this->selectedLeaf();
    if ($this->inputMode && $leaf !== null) {
      if ($leaf->handleEvent($event) || $leaf->dispatchInput($type, $event)) {
        return true;
      }
      if ($this->screenInput->dispatch($type, $leaf->instance(), $event, false)) {
        return true;
      }
    } else if ($this->screenInput->dispatch($type, $leaf?->instance(), $event)) {
      return true;
    }
    if ($type !== 'keyDown') {
      return false;
    }
    $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
    if ($this->inputMode) {
      $notification = $leaf?->instance()->releaseNotification($key, (int)$event->key->mod);
      if ($notification === null) {
        return false;
      }
      $this->release($notification);
      return true;
    }
    if ($key === \SPTK\SDLWrapper\SDL::KEY_RETURN) {
      if ($this->focus->enter()) {
        $leaf?->dispatchNotification('unselect');
        $this->selectedLeaf()?->dispatchNotification('select');
        $this->refreshStatus();
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
      $this->refreshStatus();
      return true;
    }
    if ($key === \SPTK\SDLWrapper\SDL::KEY_ESCAPE && $this->focus->leave()) {
      $leaf?->dispatchNotification('unselect');
      $this->selectedLeaf()?->dispatchNotification('select');
      $this->refreshStatus();
      return true;
    }
    $direction = match ($key) {
      \SPTK\SDLWrapper\SDL::KEY_UP => 'up',
      \SPTK\SDLWrapper\SDL::KEY_DOWN => 'down',
      \SPTK\SDLWrapper\SDL::KEY_LEFT => 'left',
      \SPTK\SDLWrapper\SDL::KEY_RIGHT => 'right',
      default => null,
    };
    if ($direction !== null) {
      $this->moveSelection($direction);
      return true;
    }
    return false;
  }

  /** Move focus between tiles while no widget is active. */
  private function moveSelection(string $direction): bool {
    $previous = $this->selectedLeaf();
    if (!$this->focus->move($direction)) {
      return false;
    }
    $previous?->dispatchNotification('unselect');
    $this->selectedLeaf()?->dispatchNotification('select');
    $this->refreshStatus();
    return true;
  }

  /** Release the active widget when a screen is hidden or an exit key is pressed. */
  public function release(string $notification = 'accept'): void {
    $this->screenInput->cancelCharacterHotkey();
    if (!$this->inputMode) {
      return;
    }
    $this->inputMode = false;
    $this->selectedLeaf()?->dispatchNotification($notification);
    $this->selectedLeaf()?->dispatchNotification('deactivate');
    $this->refreshStatus();
  }

  /** Focus and activate a widget after an in-place layout swap. */
  public function activateLeaf(LayoutLeaf $leaf): void {
    $this->selectLeaf($leaf);
    if (!$this->inputMode && $leaf->instance()->canActivate()) {
      $this->inputMode = true;
      $leaf->dispatchNotification('activate');
      $this->refreshStatus();
    }
  }

  /** Refresh the optional status bar from the current widget and input mode. */
  private function refreshStatus(): void {
    $this->statusBar?->showTip($this->selectedLeaf()?->instance()?->tip($this->inputMode) ?? '');
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->layout->measureGrid($grid);
  }

  /** Measure padded layout areas using the owning window's geometry. */
  public function measureArea(Tile $grid, WindowGeometry $geometry): void {
    $this->layout->measureArea($grid, $geometry, true);
  }

  public function drawBackgrounds(\SPTK\Rendering\PixelRenderer $renderer): void {
    $this->layout->drawBackgrounds($renderer, $this->selectedLeaf());
  }

  public function drawSeparators(\SPTK\Rendering\PixelRenderer $renderer): void {
    $this->layout->drawSeparators($renderer, $this->borderColor);
  }

  public function paint(\SPTK\Rendering\Grid $grid): void {
    $this->layout->paint($grid, $this->selectedLeaf());
  }

  /** Paint each widget's optional pixel content after the character grid. */
  public function paintPixels(\SPTK\Rendering\PixelRenderer $renderer, WindowGeometry $geometry): void {
    $selected = $this->selectedLeaf();
    $focused = $this->layout->focusedLeaves($selected);
    foreach ($this->leaves as $leaf) {
      $leaf->paintPixels($renderer, $geometry, $leaf === $selected || in_array($leaf, $focused, true));
    }
  }

  /** Return the currently selected widget leaf. */
  public function selectedLeaf(): ?\SPTK\Layout\LayoutLeaf {
    return $this->focus->selectedLeaf();
  }

  /** Return how many layout containers have been entered. */
  public function navigationDepth(): int {
    return $this->focus->depth();
  }

}
