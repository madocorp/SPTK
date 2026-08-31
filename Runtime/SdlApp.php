<?php

namespace SPTK\Runtime;

use SPTK\SDLWrapper\SDL;
use SPTK\SDLWrapper\TTF;
use SPTK\Core\Element;

/**
 * Owns SDL initialization and routes a shared event loop across app windows.
 */
class SdlApp {

  protected SDL $sdl;
  protected TTF $ttf;
  protected bool $running = false;
  protected array $pendingWindows = [];
  protected array $windows = [];
  protected array $tickers = [];
  protected int $nextTickerId = 1;
  protected array $timers = [];
  protected int $nextTimerId = 1;
  protected int $maxEventWaitMs = 50;
  protected int $inputSuppressionDepth = 0;
  protected float $inputSuppressedUntilMs = 0.0;
  protected string $appName;

  public function __construct($appName = 'SPTK') {
    $this->appName = $appName;
    cli_set_process_title($this->appName);
  }

  public function addWindow(Element $root, ?SdlWindowOptions $options = null): SdlWindow {
    if ($options === null) {
      $options = new SdlWindowOptions();
    }
    if ($options->title === false) {
      $options->title = $this->appName;
    }
    $window = new SdlWindow($root, $options);
    $this->pendingWindows[] = $window;
    return $window;
  }

  public function closeWindow(SdlWindow $window): void {
    foreach ($this->pendingWindows as $index => $pending) {
      if ($pending === $window) {
        unset($this->pendingWindows[$index]);
      }
    }
    $this->pendingWindows = array_values($this->pendingWindows);
    if (isset($this->sdl)) {
      $window->close($this->sdl);
      unset($this->windows[$window->id()]);
    }
  }

  public function addTicker(callable $ticker): int {
    $id = $this->nextTickerId++;
    $this->tickers[$id] = $ticker;
    return $id;
  }

  public function removeTicker(int $id): void {
    unset($this->tickers[$id]);
  }

  public function addTimer(int $intervalMs, callable $timer, bool $repeat = true): int {
    $intervalMs = max(1, $intervalMs);
    $now = $this->currentTimeMs();
    $id = $this->nextTimerId++;
    $this->timers[$id] = [
      'interval' => $intervalMs,
      'callback' => $timer,
      'repeat' => $repeat,
      'lastRun' => $now,
      'nextDue' => $now + $intervalMs,
      'version' => 0,
    ];
    return $id;
  }

  public function removeTimer(int $id): void {
    unset($this->timers[$id]);
  }

  public function setTimerInterval(int $id, int $intervalMs, bool $resetDue = true): bool {
    if (!isset($this->timers[$id])) {
      return false;
    }
    $intervalMs = max(1, $intervalMs);
    $this->timers[$id]['interval'] = $intervalMs;
    $this->timers[$id]['version']++;
    if ($resetDue) {
      $this->timers[$id]['nextDue'] = $this->currentTimeMs() + $intervalMs;
    }
    return true;
  }

  public function timerInterval(int $id): ?int {
    return $this->timers[$id]['interval'] ?? null;
  }

  public function setMaxEventWait(int $milliseconds): static {
    $this->maxEventWaitMs = max(0, $milliseconds);
    return $this;
  }

  public function suppressInput(): void {
    $this->inputSuppressionDepth++;
  }

  public function resumeInput(int $discardQueuedInputMs = 150): void {
    $this->inputSuppressionDepth = max(0, $this->inputSuppressionDepth - 1);
    $this->inputSuppressedUntilMs = max($this->inputSuppressedUntilMs, $this->currentTimeMs() + max(0, $discardQueuedInputMs));
  }

  public function quit(): void {
    $this->running = false;
  }

  public function run(): void {
    try {
      $this->boot();
      $this->running = true;
      $this->render();
      $this->eventLoop();
    } finally {
      $this->shutdown();
    }
  }

  protected function boot(): void {
    $this->sdl = new SDL();
    $this->ttf = new TTF();
    if (!$this->sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO)) {
      throw new \RuntimeException('SDL_Init failed: ' . $this->sdl->error());
    }
    if (!$this->ttf->ffi->TTF_Init()) {
      throw new \RuntimeException('TTF_Init failed.');
    }
    $this->openPendingWindows();
    if (empty($this->windows)) {
      throw new \RuntimeException('SdlApp needs at least one window before run().');
    }
    if (function_exists('pcntl_signal')) {
      pcntl_signal(SIGINT, fn() => $this->running = false);
    }
  }

  protected function eventLoop(): void {
    $event = $this->sdl->ffi->new('SDL_Event');
    while ($this->running && !empty($this->windows)) {
      $hasEvent = $this->sdl->ffi->SDL_WaitEventTimeout(\FFI::addr($event), $this->eventWaitTimeoutMs());
      if ($hasEvent) {
        do {
          $this->handleSdlEvent($event);
        } while ($this->running && $this->sdl->ffi->SDL_PollEvent(\FFI::addr($event)));
      }
      if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
      }
      $this->runTickers();
      $this->runTimers();
      $this->pruneClosedWindows();
      $this->openPendingWindows();
      $this->render();
    }
  }

  protected function openPendingWindows(): void {
    foreach ($this->pendingWindows as $window) {
      if ($window->isClosed()) {
        continue;
      }
      $window->open($this->sdl, $this->ttf);
      $this->windows[$window->id()] = $window;
    }
    $this->pendingWindows = [];
  }

  protected function runTickers(): void {
    foreach ($this->tickers as $id => $ticker) {
      if ($ticker() === false) {
        unset($this->tickers[$id]);
      }
    }
  }

  protected function runTimers(?float $now = null): void {
    $now ??= $this->currentTimeMs();
    foreach ($this->timers as $id => $timer) {
      if (!isset($this->timers[$id]) || $now < $timer['nextDue']) {
        continue;
      }
      $version = $timer['version'];
      $result = ($timer['callback'])($id, $now, $now - $timer['lastRun'], $this);
      if (!isset($this->timers[$id]) || $result === false || !$timer['repeat']) {
        unset($this->timers[$id]);
        continue;
      }
      if (is_int($result) && $result > 0) {
        $this->timers[$id]['interval'] = $result;
        $this->timers[$id]['version']++;
        $this->timers[$id]['nextDue'] = $now + $result;
      } else if ($this->timers[$id]['version'] === $version) {
        $this->timers[$id]['nextDue'] = $now + $this->timers[$id]['interval'];
      }
      if (isset($this->timers[$id])) {
        $this->timers[$id]['lastRun'] = $now;
      }
    }
  }

  protected function eventWaitTimeoutMs(?float $now = null): int {
    $now ??= $this->currentTimeMs();
    $wait = $this->maxEventWaitMs;
    foreach ($this->timers as $timer) {
      $timerWait = max(0, (int)ceil($timer['nextDue'] - $now));
      $wait = min($wait, $timerWait);
    }
    return $wait;
  }

  protected function currentTimeMs(): float {
    return microtime(true) * 1000;
  }

  protected function handleSdlEvent(mixed $event): void {
    if ($event->type === SDL::SDL_QUIT) {
      $this->running = false;
      return;
    }
    if ($this->inputSuppressed() && $this->inputEvent($event)) {
      return;
    }
    $windowId = $this->eventWindowId($event);
    if ($windowId !== 0 && isset($this->windows[$windowId])) {
      $this->windows[$windowId]->handleEvent($this->sdl, $event);
    }
  }

  protected function inputSuppressed(?float $now = null): bool {
    $now ??= $this->currentTimeMs();
    return $this->inputSuppressionDepth > 0 || $now < $this->inputSuppressedUntilMs;
  }

  protected function inputEvent(mixed $event): bool {
    return in_array($event->type, [
      SDL::SDL_EVENT_TEXT_INPUT,
      SDL::SDL_EVENT_KEY_DOWN,
      SDL::SDL_EVENT_KEY_UP,
    ], true);
  }

  protected function eventWindowId(mixed $event): int {
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

  protected function pruneClosedWindows(): void {
    foreach ($this->windows as $id => $window) {
      if ($window->isClosed()) {
        unset($this->windows[$id]);
      }
    }
  }

  protected function render(): void {
    foreach ($this->windows as $window) {
      $window->render();
    }
  }

  protected function shutdown(): void {
    if (isset($this->sdl)) {
      foreach ($this->windows as $window) {
        $window->close($this->sdl);
      }
      foreach ($this->pendingWindows as $window) {
        if (!$window->isClosed()) {
          $window->close($this->sdl);
        }
      }
    }
    $this->windows = [];
    $this->pendingWindows = [];
    if (isset($this->ttf)) {
      $this->ttf->ffi->TTF_Quit();
    }
    if (isset($this->sdl)) {
      $this->sdl->ffi->SDL_Quit();
    }
  }

}
