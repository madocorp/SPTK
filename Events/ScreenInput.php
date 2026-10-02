<?php

namespace SPTK\Events;

use SPTK\Core\Widget;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Button\Button;

/** Dispatches screen actions and registered button hotkeys for raw input. */
final class ScreenInput {

  private EventDispatcher $dispatcher;
  private array $hotkeys = [];
  private ?array $pendingCharacterHotkey = null;

  /** Retain the screen's XML event declarations. */
  public function __construct(private array $events) {
    $this->dispatcher = new EventDispatcher();
  }

  /** Register one button's screen-wide hotkey. */
  public function register(Button $button): void {
    $key = $button->hotkey();
    if ($key === null) {
      return;
    }
    if (isset($this->hotkeys[$key])) {
      throw new \RuntimeException("Duplicate button hotkey: {$key}");
    }
    $this->hotkeys[$key] = $button;
  }

  /** Hold a printable button hotkey until SDL delivers its text input. */
  public function deferCharacterHotkey(mixed $event, bool $allow): bool {
    if (!$allow || $event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    foreach ($this->hotkeys as $key => $button) {
      if (strlen($key) === 1 && ctype_alnum($key) && (new EventDefinition('keyDown', $key, ''))->matches($event)) {
        $keydown = $event instanceof \FFI\CData ? new KeyboardEvent($event) : $event;
        $this->pendingCharacterHotkey = [$button, $keydown, KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod)];
        return true;
      }
    }
    return false;
  }

  /** Press a deferred hotkey after the widget has received text, or on key release if no text arrives. */
  public function finishCharacterHotkey(mixed $event): bool {
    if ($this->pendingCharacterHotkey === null) {
      return false;
    }
    if ($event->type === SDL::SDL_EVENT_KEY_UP && KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod) !== $this->pendingCharacterHotkey[2]) {
      return false;
    }
    if ($event->type !== SDL::SDL_EVENT_TEXT_INPUT && $event->type !== SDL::SDL_EVENT_KEY_UP) {
      return false;
    }
    [$button, $keydown] = $this->pendingCharacterHotkey;
    $this->pendingCharacterHotkey = null;
    $button->press($keydown);
    return true;
  }

  public function cancelCharacterHotkey(): void {
    $this->pendingCharacterHotkey = null;
  }

  /** Dispatch matching screen events and button hotkeys. */
  public function dispatch(string $type, ?Widget $widget, mixed $event, bool $allowHotkeys = true): bool {
    if ($this->dispatcher->dispatch($this->events, new EventContext($type, $widget, $event), true)) {
      if ($type === 'keyDown') {
        $this->pendingCharacterHotkey = null;
      }
      return true;
    }
    if ($allowHotkeys && $type === 'keyDown') {
      foreach ($this->hotkeys as $key => $button) {
        if ((new EventDefinition('keyDown', $key, ''))->matches($event)) {
          if ($this->pendingCharacterHotkey !== null && $this->pendingCharacterHotkey[0] === $button) {
            return true;
          }
          $button->press($event);
          return true;
        }
      }
    }
    return false;
  }

  /** Map an SDL event to its XML input type. */
  public function type(mixed $event): ?string {
    return match ($event->type) {
      SDL::SDL_EVENT_KEY_DOWN => 'keyDown',
      SDL::SDL_EVENT_KEY_UP => 'keyUp',
      SDL::SDL_EVENT_TEXT_INPUT => 'textInput',
      default => null,
    };
  }

}
