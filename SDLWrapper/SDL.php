<?php

namespace SPTK\SDLWrapper;

use \SPTK\SDLWrapper\KeyCombo;

class SDL {

  const SDL_INIT_AUDIO = 0x10;
  const SDL_INIT_VIDEO = 0x20;

  const SDL_QUIT = 0x100;
  const SDL_EVENT_WINDOW_EXPOSED = 0x204;
  const SDL_EVENT_WINDOW_RESIZED = 0x206;
  const SDL_EVENT_WINDOW_MAXIMIZED = 0x20a;
  const SDL_EVENT_WINDOW_RESTORED = 0x20b;
  const SDL_EVENT_WINDOW_CLOSE_REQUESTED = 0x210;

  const SDL_EVENT_KEY_DOWN = 0x300;
  const SDL_EVENT_KEY_UP = 0x301;
  const SDL_EVENT_TEXT_INPUT = 0x303;

  const SDL_PIXELFORMAT_RGBA8888 = 0x16462004; // ((1 << 28) | (6 << 24) | (4 << 20) | (6 << 16) | (32 << 8) | (4 << 0));
  const SDL_TEXTUREACCESS_STATIC = 0;
  const SDL_TEXTUREACCESS_STREAMING = 1;
  const SDL_TEXTUREACCESS_TARGET = 2;
  const SDL_BLENDMODE_BLEND = 0x1;
  const SDL_SCALE_MODE_NEAREST = 0;

  public static $instance;

  public $sdl;
  private $waitForEvent = 100; // ms
  private $timerPeriod = 1000; // ms
  private $eventCallback = null;
  private $loopCallback = null;
  private $timerCallback = null;
  private $endCallback = null;
  private $end = false;
  private $supressTextInput;
  private $syncEvents = false;

  public function __construct(?callable $initCallback) {
    if (!is_null(self::$instance)) {
      throw new \Exception("SPTK\\SDL is a singleton, you can't instantiate more than once");
    }
    self::$instance = $this;
    pcntl_signal(SIGINT, [$this, 'sigIntHandler']);
    $dir = \SPTK\App::$instance->getDir();
    $this->sdl = \FFI::cdef(file_get_contents(SPTK_PATH . "/SDLWrapper/sdl_extract.h"), SPTK_PATH . "/SDLWrapper/libSDL3.so");
    $this->sdl->SDL_Init(self::SDL_INIT_VIDEO);
    KeyCombo::init();
    if ($initCallback !== null) {
      call_user_func($initCallback, $this);
    }
    try {
      $this->eventLoop();
    } catch (\Exception $e) {
      echo "ERROR: Uncaught exception in the event loop!\n";
      echo '       ' . $e->getMessage(), "\n";
    }
    if ($this->endCallback !== null) {
      call_user_func($this->endCallback);
    }
    $this->sdl->SDL_Quit();
  }

  public function sigIntHandler(int $signo, mixed $siginfo): void {
    $this->end = true;
  }

  protected function eventLoop(): void {
    $event = $this->sdl->new('SDL_Event');
    $timer = microtime(true) * 1000;
    while (!$this->end) {
      $hasEvent = $this->sdl->SDL_WaitEventTimeout(\FFI::addr($event), $this->waitForEvent);
      if ($hasEvent) {
        do {
          $parsedEvent = $this->parseEvent($event);
          if ($this->supressTextInput && $parsedEvent['name'] === 'TextInput') {
            continue;
          }
          if ($this->eventCallback !== false) {
            \SPTK\Element::beginBatch();
            try {
              call_user_func($this->eventCallback, $parsedEvent);
            } finally {
              \SPTK\Element::endBatch();
            }
          }
        } while (!$this->end && $this->sdl->SDL_PollEvent(\FFI::addr($event)));
      }
      if ($this->loopCallback !== null) {
        \SPTK\Element::beginBatch();
        try {
          call_user_func($this->loopCallback);
        } finally {
          \SPTK\Element::endBatch();
        }
      }
      pcntl_signal_dispatch();
      if ($this->timerCallback !== null) {
        $now = microtime(true) * 1000;
        if ($this->syncEvents) {
          while ($now < $timer + $this->timerPeriod) {
            usleep($timer + $this->timerPeriod - $now);
            $now = microtime(true) * 1000;
          }
        }
        if ($now >= $timer + $this->timerPeriod) {
          \SPTK\Element::beginBatch();
          try {
            call_user_func($this->timerCallback, $now);
          } finally {
            \SPTK\Element::endBatch();
          }
          $timer = $now;
        }
      }
      $this->supressTextInput = false;
    }
  }

  public function syncEvents(bool $sync = true): void {
    $this->syncEvents = $sync;
  }

  public function supressTextInput(bool $supress = true): void {
    $this->supressTextInput = $supress;
  }

  public function parseEvent(\FFI\CData $event): array {
    $parsedEvent = [];
    $data = false;
    switch ($event->type) {
      case SDL::SDL_EVENT_KEY_DOWN:
        $parsedEvent = $this->keyboardEventToArray($event->key);
        $parsedEvent['name'] = 'KeyPress';
        break;
      case SDL::SDL_EVENT_KEY_UP:
        $parsedEvent = $this->keyboardEventToArray($event->key);
        $parsedEvent['name'] = 'KeyRelease';
        break;
      case SDL::SDL_EVENT_TEXT_INPUT:
        $parsedEvent = $this->textInputEventToArray($event->text);
        $parsedEvent['name'] = 'TextInput';
        break;
      case SDL::SDL_EVENT_WINDOW_EXPOSED:
      case SDL::SDL_EVENT_WINDOW_MAXIMIZED:
      case SDL::SDL_EVENT_WINDOW_RESTORED:
      case SDL::SDL_EVENT_WINDOW_RESIZED:
      case SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED:
        $parsedEvent = $this->windowEventToArray($event->window);
        $parsedEvent['name'] = 'WindowEvent';
        break;
      default:
        $parsedEvent['type'] = $event->type;
        break;
    }
    return $parsedEvent;
  }

  private function keyboardEventToArray(\FFI\CData  $keyEvent): array {
    return [
      'type' => $keyEvent->type,
      'timestamp' => $keyEvent->timestamp,
      'windowID' => $keyEvent->windowID,
      'which' => $keyEvent->which,
      'scancode' => $keyEvent->scancode,
      'key' => $keyEvent->key,
      'mod' => $keyEvent->mod,
      'raw' => $keyEvent->raw,
      'down' => (bool)$keyEvent->down,
      'repeat' => (bool)$keyEvent->repeat
    ];
  }

  private function textInputEventToArray(\FFI\CData  $textInputEvent): array {
    return [
      'type' => $textInputEvent->type,
      'timestamp' => $textInputEvent->timestamp,
      'windowID' => $textInputEvent->windowID,
      'text' => $textInputEvent->text
    ];
  }

  private function windowEventToArray(\FFI\CData $windowEvent): array {
    return [
      'type' => $windowEvent->type,
      'timestamp' => $windowEvent->timestamp,
      'windowID' => $windowEvent->windowID,
      'data1' => $windowEvent->data1,
      'data2' => $windowEvent->data2
    ];
  }

  public function end(): void {
    $this->end = true;
  }

  public function setTimer(int $timerPeriod): void {
    $this->timerPeriod = $timerPeriod;
    if ($this->timerPeriod < $this->waitForEvent) {
      throw new \Exception('TimerPeriod must be greater than waitForEvent!');
    }
  }

  public function setWaitTime(int $waitForEvent): void {
    $this->waitForEvent = $waitForEvent;
    if ($this->timerPeriod < $this->waitForEvent) {
      throw new \Exception('TimerPeriod must be greater than waitForEvent!');
    }
  }

  public function setEventCallback(?callable $callback): void {
    $this->eventCallback = $callback;
  }

  public function setLoopCallback(?callable $callback): void {
    $this->loopCallback = $callback;
  }

  public function setTimerCallback(?callable $callback): void {
    $this->timerCallback = $callback;
  }

  public function setEndCallback(?callable $callback): void {
    $this->endCallback = $callback;
  }

}
