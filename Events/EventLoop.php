<?php

namespace SPTK\Events;

use \SPTK\SDLWrapper\SDL;
use SPTK\Core\Window;

final class EventLoop {

  private $windows = [];
  private $running;
  private $eventTimeout = 100;
  private $sdl;

  public function __construct() {
    $this->sdl = \SPTK\App::sdl();
  }

  public function registerWindow(Window $window) {
    $windowId = $window->id();
    $this->windows[$windowId] = $window;
  }

  public function quitWindow(int $windowId) {
    unset($this->windows[$windowId]);
  }

  public function start(): void {
    $this->running = true;
    $event = $this->sdl->ffi->new('SDL_Event');
    while ($this->running && !empty($this->windows)) {
      $hasEvent = $this->sdl->ffi->SDL_WaitEventTimeout(\FFI::addr($event), $this->eventTimeout);
      if ($hasEvent) {
        do {
          $this->handleSdlEvent($event);
        } while ($this->running && $this->sdl->ffi->SDL_PollEvent(\FFI::addr($event)));
      }
      pcntl_signal_dispatch();
    }
  }

  private function handleSdlEvent(mixed $event): void {
    if ($event->type === SDL::SDL_QUIT) {
      $this->running = false;
      return;
    }
    $windowId = $this->eventWindowId($event);
    if ($windowId !== 0 && isset($this->windows[$windowId])) {
      $this->windows[$windowId]->handleEvent($event);
    }
  }

  public function stop(): void {
    $this->running = false;
  }

  private function inputEvent(mixed $event): bool {
    return in_array($event->type, [
      SDL::SDL_EVENT_TEXT_INPUT,
      SDL::SDL_EVENT_KEY_DOWN,
      SDL::SDL_EVENT_KEY_UP,
    ], true);
  }

  private function eventWindowId(mixed $event): int {
    return match ($event->type) {
      SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED,
      SDL::SDL_EVENT_WINDOW_RESIZED,
      SDL::SDL_EVENT_WINDOW_MAXIMIZED,
      SDL::SDL_EVENT_WINDOW_RESTORED,
      SDL::SDL_EVENT_WINDOW_EXPOSED => (int)$event->window->windowID,
      SDL::SDL_EVENT_TEXT_INPUT => (int)$event->text->windowID,
      SDL::SDL_EVENT_KEY_DOWN,
      SDL::SDL_EVENT_KEY_UP => (int)$event->key->windowID,
      default => 0,
    };
  }

}
