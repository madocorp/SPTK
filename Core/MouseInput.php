<?php

namespace SPTK\Core;

use SPTK\Layout\{LayoutLeaf, WindowGeometry};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Choice\Choice;
use SPTK\Widgets\FileSelector\FileSelector;
use SPTK\Widgets\Image\Image;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\Table\Table;
use SPTK\Widgets\TextEditor\TextEditor;

/** Maps clicks and wheel steps onto the screen's existing widget interactions. */
final class MouseInput {

  private ?LayoutLeaf $pressed = null;
  private ?LayoutLeaf $wheelLeaf = null;
  private float $wheelRemainder = 0.0;

  /** Bind mouse input to one screen's selection state. */
  public function __construct(private Screen $screen) {
  }

  /** Forget a pending click or fractional wheel step after a layout change. */
  public function reset(): void {
    $this->pressed = null;
    $this->wheelLeaf = null;
    $this->wheelRemainder = 0.0;
  }

  /** Route one SDL button or wheel event without entering screen keyboard dispatch. */
  public function handle(mixed $event, WindowGeometry $geometry): bool {
    return match ($event->type) {
      SDL::SDL_EVENT_MOUSE_BUTTON_DOWN => $this->press($event, $geometry),
      SDL::SDL_EVENT_MOUSE_BUTTON_UP => $this->release($event, $geometry),
      SDL::SDL_EVENT_MOUSE_WHEEL => $this->wheel($event, $geometry),
      default => false,
    };
  }

  /** Remember the visible tile beneath a left button press. */
  private function press(mixed $event, WindowGeometry $geometry): bool {
    if ((int)$event->button->button !== SDL::SDL_BUTTON_LEFT || (int)$event->button->clicks > 1) {
      $this->pressed = null;
      return false;
    }
    $this->pressed = $this->screen->layout->hitTest((float)$event->button->x, (float)$event->button->y, $geometry);
    return false;
  }

  /** Complete a click only when button release hits its original tile. */
  private function release(mixed $event, WindowGeometry $geometry): bool {
    if ((int)$event->button->button !== SDL::SDL_BUTTON_LEFT) {
      return false;
    }
    $pressed = $this->pressed;
    $this->pressed = null;
    if ($pressed === null || (int)$event->button->clicks > 1) {
      return false;
    }
    $target = $this->screen->layout->hitTest((float)$event->button->x, (float)$event->button->y, $geometry);
    return $target === $pressed && $this->screen->activatePointerLeaf($target, $event);
  }

  /** Move only the active built-in widget by its existing keyboard steps. */
  private function wheel(mixed $event, WindowGeometry $geometry): bool {
    $active = $this->screen->activeLeaf();
    $target = $this->screen->layout->hitTest((float)$event->wheel->mouse_x, (float)$event->wheel->mouse_y, $geometry);
    if ($active === null || $target !== $active || !$this->supportsWheel($active)) {
      $this->wheelLeaf = null;
      $this->wheelRemainder = 0.0;
      return false;
    }
    if ($this->wheelLeaf !== $active) {
      $this->wheelLeaf = $active;
      $this->wheelRemainder = 0.0;
    }
    $delta = (float)$event->wheel->y * ((int)$event->wheel->direction === SDL::SDL_MOUSEWHEEL_FLIPPED ? -1 : 1);
    if (!is_finite($delta)) {
      return false;
    }
    $this->wheelRemainder += $delta;
    $steps = min(32, (int)floor(abs($this->wheelRemainder)));
    if ($steps === 0) {
      return false;
    }
    $key = $this->wheelRemainder > 0 ? SDL::KEY_UP : SDL::KEY_DOWN;
    $this->wheelRemainder += $this->wheelRemainder > 0 ? -$steps : $steps;
    $keyboard = (object)['type' => SDL::SDL_EVENT_KEY_DOWN,
      'key' => (object)['key' => $key, 'mod' => 0, 'scancode' => 0, 'repeat' => false, 'down' => true]];
    $handled = false;
    for ($step = 0; $step < $steps; $step++) {
      $handled = $active->handleEvent($keyboard) || $handled;
    }
    return $handled;
  }

  /** Keep wheel mapping away from custom canvases and value editors. */
  private function supportsWheel(LayoutLeaf $leaf): bool {
    $widget = $leaf->instance();
    return $widget instanceof ListView || $widget instanceof Table || $widget instanceof TextEditor
      || $widget instanceof FileSelector || $widget instanceof Choice || $widget instanceof Image;
  }

}
