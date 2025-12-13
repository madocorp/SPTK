<?php

namespace SPTK;

class SDL {

  const SDL_INIT_AUDIO = 0x10;
  const SDL_INIT_VIDEO = 0x20;

  const SDL_QUIT = 0x100;
  const SDL_EVENT_WINDOW_EXPOSED = 0x204;
  const SDL_EVENT_WINDOW_RESIZED = 0x206;
  const SDL_EVENT_KEY_DOWN = 0x300;
  const SDL_EVENT_KEY_UP = 0x301;
  const SDL_EVENT_TEXT_INPUT = 0x303;

  const SDL_PIXELFORMAT_RGBA8888 = ((1 << 28) | (6 << 24) | (4 << 20) | (6 << 16) | (32 << 8) | (4 << 0));
  const SDL_TEXTUREACCESS_STATIC = 0;
  const SDL_TEXTUREACCESS_STREAMING = 1;
  const SDL_TEXTUREACCESS_TARGET = 2;
  const SDL_BLENDMODE_BLEND = 0x1;
  const SDL_SCALE_MODE_NEAREST= 0;

  public static $instance;

  public $sdl;
  private $waitForEvent = 100;
  private $timerPeriod = 1000000;
  private $eventCallback;
  private $timerCallback;
  private $end = false;

  public function __construct($initCallback, $timerCallback, $eventCallback, $endCallback) {
    if (!is_null(self::$instance)) {
      throw new \Exception("SPTK\\SDL is a singleton, you can't instantiate more than once");
    }
    self::$instance = $this;
    $this->eventCallback = $eventCallback;
    $this->timerCallback = $timerCallback;
    pcntl_signal(SIGINT, [$this, 'sigIntHandler']);
    $dir = App::$instance->getDir();
    $this->sdl = \FFI::cdef(file_get_contents("{$dir}/SDLWrapper/sdl_extract.h"), "{$dir}/SDLWrapper/libSDL3.so");
    $this->sdl->SDL_Init(self::SDL_INIT_VIDEO);
    call_user_func($initCallback);
    $this->eventLoop();
    call_user_func($endCallback);
    $this->sdl->SDL_Quit();
  }

  public function sigIntHandler($signo, $siginfo) {
    $this->end = true;
  }

  protected function eventLoop() {
    $event = $this->sdl->new('SDL_Event');
    $timer = microtime(true) * 1000000;
    while (!$this->end) {
      $hasEvent = true;
      while ($hasEvent !== false && !$this->end) {
        $hasEvent = $this->sdl->SDL_PollEvent(\FFI::addr($event));
        if ($hasEvent !== false) {
          $parsedEvent = $this->parseEvent($event);
          call_user_func($this->eventCallback, $parsedEvent);
        }
      }
      pcntl_signal_dispatch();
      $now = microtime(true) * 1000000;
      if ($now > $timer + $this->timerPeriod) {
        call_user_func($this->timerCallback, $now);
        $timer = $now;
      } else {
        usleep($this->waitForEvent);
      }
    }
  }

  public function parseEvent($event) {
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
      default:
        $parsedEvent['type'] = $event->type;
        break;
    }
    return $parsedEvent;
  }

  private function keyboardEventToArray($keyEvent) {
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

  private function textInputEventToArray($textInputEvent) {
    return [
      'type' => $textInputEvent->type,
      'timestamp' => $textInputEvent->timestamp,
      'windowID' => $textInputEvent->windowID,
      'text' => $textInputEvent->text
    ];
  }

  public function end() {
    $this->end = true;
  }

  public function setTimer(int $timerPeriod, int $waitForEvent) {
    if ($timerPeriod < $waitForEvent) {
      throw new Exception('TimerPeriod must be greater than waitForEvent!');
    }
    $this->timerPeriod = $timerPeriod;
    $this->waitForEvent = $waitForEvent;
  }

}
