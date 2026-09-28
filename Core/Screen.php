<?php

namespace SPTK\Core;

use SPTK\Events\{EventContext, EventDispatcher, KeyNormalizer};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile, WindowGeometry};
use SPTK\Widgets\Button\Button;

/** Owns one screen's layout, widget selection, and screen-level input. */
final class Screen {

  private array $leaves;
  private WidgetSelection $selection;
  private bool $inputMode = false;
  private bool $initialSelectionNotified = false;
  private array $events;
  private EventDispatcher $eventDispatcher;
  private array $hotkeys = [];
  private array $widgetsById = [];

  /** Create a screen and attach screen-level event subscriptions to its leaves. */
  public function __construct(public LayoutNode $layout, public Color $borderColor = new Color(85, 85, 85), array $events = [], public string $id = '', public string $title = '') {
    $this->events = $events;
    $this->eventDispatcher = new EventDispatcher();
    $this->indexWidgets();
  }

  /** Replace the root layout after adding a window-level selector. */
  public function setLayout(LayoutNode $layout): void {
    $this->layout = $layout;
    $this->indexWidgets();
  }

  /** Index widget IDs and register button hotkeys at screen scope. */
  private function indexWidgets(): void {
    $this->leaves = $this->layout->leaves();
    $this->selection = new WidgetSelection($this->leaves);
    $this->hotkeys = [];
    $this->widgetsById = [];
    foreach ($this->leaves as $leaf) {
      $leaf->setScreenEvents($this->events);
      $widget = $leaf->instance();
      if ($widget->id() !== null) {
        if (isset($this->widgetsById[$widget->id()])) {
          throw new \RuntimeException("Duplicate widget id: {$widget->id()}");
        }
        $this->widgetsById[$widget->id()] = $widget;
      }
      if ($widget instanceof Button && $widget->hotkey() !== null) {
        if (isset($this->hotkeys[$widget->hotkey()])) {
          throw new \RuntimeException("Duplicate button hotkey: {$widget->hotkey()}");
        }
        $this->hotkeys[$widget->hotkey()] = $widget;
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
  private function selectLeaf(LayoutLeaf $leaf): bool {
    $previous = $this->selectedLeaf();
    if (!$this->selection->select($leaf)) {
      return false;
    }
    if ($this->initialSelectionNotified) {
      $previous?->dispatchNotification('unselect');
      $leaf->dispatchNotification('select');
    }
    return true;
  }

  /** Route input through the active widget, screen subscriptions, and screen controls. */
  public function handleEvent(mixed $event): bool {
    $type = $this->inputType($event);
    if ($type === null) {
      return false;
    }
    if (!$this->initialSelectionNotified) {
      $this->selectedLeaf()?->dispatchNotification('select');
      $this->initialSelectionNotified = true;
    }
    $leaf = $this->selectedLeaf();
    if ($this->inputMode && $leaf !== null) {
      if ($leaf->handleEvent($event) || $leaf->dispatchInput($type, $event)) {
        return true;
      }
      if ($this->dispatchInput($type, $leaf->instance(), $event)) {
        return true;
      }
    } else if ($this->dispatchInput($type, $leaf?->instance(), $event)) {
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
    if (!$this->selection->move($direction)) {
      return false;
    }
    $previous?->dispatchNotification('unselect');
    $this->selectedLeaf()?->dispatchNotification('select');
    return true;
  }

  /** Release the active widget when a screen is hidden or an exit key is pressed. */
  public function release(string $notification = 'accept'): void {
    if (!$this->inputMode) {
      return;
    }
    $this->inputMode = false;
    $this->selectedLeaf()?->dispatchNotification($notification);
    $this->selectedLeaf()?->dispatchNotification('deactivate');
  }

  /** Run matching screen event declarations for keyboard and text input. */
  private function dispatchInput(string $type, ?\SPTK\Core\Widget $widget, mixed $event): bool {
    if ($this->eventDispatcher->dispatch($this->events, new EventContext($type, $widget, $event), true)) {
      return true;
    }
    if ($type === 'keyDown') {
      foreach ($this->hotkeys as $key => $button) {
        if ((new \SPTK\Events\EventDefinition('keyDown', $key, ''))->matches($event)) {
          $button->press($event);
          return true;
        }
      }
    }
    return false;
  }

  /** Map an SDL input event to its XML event type. */
  private function inputType(mixed $event): ?string {
    return match ($event->type) {
      \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN => 'keyDown',
      \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_UP => 'keyUp',
      \SPTK\SDLWrapper\SDL::SDL_EVENT_TEXT_INPUT => 'textInput',
      default => null,
    };
  }

  public function measureGrid(\SPTK\Layout\Tile $grid) {
    $this->layout->measureGrid($grid);
  }

  /** Measure padded layout areas using the owning window's geometry. */
  public function measureArea(Tile $grid, WindowGeometry $geometry): void {
    $this->layout->measureArea($grid, $geometry);
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
    foreach ($this->leaves as $leaf) {
      $leaf->paintPixels($renderer, $geometry, $leaf === $selected);
    }
  }

  /** Return the currently selected widget leaf. */
  public function selectedLeaf(): ?\SPTK\Layout\LayoutLeaf {
    return $this->selection->selectedLeaf();
  }

}
