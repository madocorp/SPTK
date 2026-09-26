<?php

namespace SPTK\Core;

use SPTK\Events\{EventContext, EventDispatcher, KeyNormalizer};

final class Screen {

  private array $leaves;
  private WidgetSelection $selection;
  private bool $inputMode = false;
  private bool $initialSelectionNotified = false;
  private array $events;
  private EventDispatcher $eventDispatcher;

  /** Create a screen and attach screen-level event subscriptions to its leaves. */
  public function __construct(public \SPTK\Layout\LayoutNode $layout, public Color $borderColor = new Color(85, 85, 85), array $events = []) {
    $this->leaves = $layout->leaves();
    $this->selection = new WidgetSelection($this->leaves);
    $this->events = $events;
    $this->eventDispatcher = new EventDispatcher();
    foreach ($this->leaves as $leaf) {
      $leaf->setScreenEvents($events);
    }
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
      $this->inputMode = true;
      $this->selectedLeaf()?->dispatchNotification('activate');
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
    return $this->eventDispatcher->dispatch($this->events, new EventContext($type, $widget, $event), true);
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

  public function measureArea(\SPTK\Layout\Tile $grid, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY, int $windowWidth, int $windowHeight): void {
    $this->layout->measureArea($grid, $cellWidth, $cellHeight, $offsetX, $offsetY, $windowWidth, $windowHeight);
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
  public function paintPixels(\SPTK\Rendering\PixelRenderer $renderer, int $cellWidth, int $cellHeight, int $offsetX, int $offsetY): void {
    $selected = $this->selectedLeaf();
    foreach ($this->leaves as $leaf) {
      $leaf->paintPixels($renderer, $cellWidth, $cellHeight, $offsetX, $offsetY, $leaf === $selected);
    }
  }

  /** Return the currently selected widget leaf. */
  private function selectedLeaf(): ?\SPTK\Layout\LayoutLeaf {
    return $this->selection->selectedLeaf();
  }

}
