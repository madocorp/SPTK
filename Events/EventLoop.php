<?php

namespace SPTK\Events;

use \SPTK\SDLWrapper\SDL;
use SPTK\Core\Window;

final class EventLoop {

  private $windows = [];
  private $running;
  private $eventTimeout = 1000;
  private $sdl;
  private $timers = [];
  private $nextTimerId = 1;

  public function __construct() {
    $this->sdl = \SPTK\App::sdl();
  }

  public function registerWindow(Window $window) {
    $windowId = $window->id();
    $this->windows[$windowId] = $window;
  }

  /** Register a repeating timer action and return its handle. */
  public function addTimer(string $action, int $period): int {
    if ($period < 1) {
      throw new \InvalidArgumentException('Timer period must be positive.');
    }
    $id = $this->nextTimerId++;
    $this->timers[$id] = [
      'action' => $action,
      'period' => $period,
      'deadline' => hrtime(true) + $period * 1000000,
    ];
    return $id;
  }

  /** Change a timer period and restart its countdown. */
  public function setTimerPeriod(int|string $timerId, int $period): void {
    if ($period < 1) {
      throw new \InvalidArgumentException('Timer period must be positive.');
    }
    $timerIds = $this->timerIds($timerId);
    if ($timerIds === []) {
      throw new \OutOfBoundsException("Unknown timer: {$timerId}");
    }
    foreach ($timerIds as $id) {
      $this->timers[$id]['period'] = $period;
      $this->timers[$id]['deadline'] = hrtime(true) + $period * 1000000;
    }
  }

  /** Remove a timer by its handle. */
  public function removeTimer(int|string $timerId): void {
    foreach ($this->timerIds($timerId) as $id) {
      unset($this->timers[$id]);
    }
  }

  /** Resolve a timer handle or all timers registered for one action. */
  private function timerIds(int|string $timerId): array {
    if (is_int($timerId)) {
      return isset($this->timers[$timerId]) ? [$timerId] : [];
    }
    $timerIds = [];
    foreach ($this->timers as $id => $timer) {
      if ($timer['action'] === $timerId) {
        $timerIds[] = $id;
      }
    }
    return $timerIds;
  }

  public function quitWindow(int $windowId) {
    unset($this->windows[$windowId]);
  }

  public function start(): void {
    $this->running = true;
    $now = hrtime(true);
    foreach ($this->timers as &$timer) {
      $timer['deadline'] = $now + $timer['period'] * 1000000;
    }
    unset($timer);
    $event = $this->sdl->ffi->new('SDL_Event');
    while ($this->running && !empty($this->windows)) {
      $hasEvent = $this->sdl->ffi->SDL_WaitEventTimeout(\FFI::addr($event), $this->waitTimeout());
      if ($hasEvent) {
        do {
          $this->handleSdlEvent($event);
        } while ($this->running && $this->sdl->ffi->SDL_PollEvent(\FFI::addr($event)));
      }
      if ($this->running) {
        $this->dispatchDueTimers();
      }
      pcntl_signal_dispatch();
    }
  }

  /** Bound the SDL wait by the nearest timer deadline. */
  private function waitTimeout(): int {
    if ($this->timers === []) {
      return $this->eventTimeout;
    }
    $deadline = min(array_column($this->timers, 'deadline'));
    $remaining = $deadline - hrtime(true);
    return max(1, min($this->eventTimeout, (int)ceil($remaining / 1000000)));
  }

  /** Dispatch expired timers and advance their deadlines without accumulating drift. */
  private function dispatchDueTimers(): void {
    $now = hrtime(true);
    foreach (array_keys($this->timers) as $timerId) {
      if (!isset($this->timers[$timerId]) || $this->timers[$timerId]['deadline'] > $now) {
        continue;
      }
      $timer = $this->timers[$timerId];
      $periodNs = $timer['period'] * 1000000;
      $elapsedPeriods = intdiv($now - $timer['deadline'], $periodNs) + 1;
      $this->timers[$timerId]['deadline'] += $elapsedPeriods * $periodNs;
      $event = new EventDefinition('timer', null, $timer['action']);
      (new EventDispatcher())->dispatch([$event], new EventContext('timer'), false);
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
