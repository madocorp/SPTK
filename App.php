<?php

namespace SPTK;

final class App {

  private static $instance;
  private $eventLoop;
  private $sdl;
  private $ttf;
  private $font;
  private $events = [];
  private $initialized = false;

  public static function load(string $class): void {
    $prefix = 'SPTK\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $path = APP_DIR . '/SPTK/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
      require_once $path;
    }
  }

  public static function sdl() {
    return self::$instance?->sdl;
  }

  public static function ttf() {
    return self::$instance->ttf;
  }

  public static function font() {
    return self::$instance->font;
  }

  /** Return the current font when layout measurement runs after app initialization. */
  public static function fontOrNull(): ?Rendering\Font {
    return self::$instance?->font;
  }

  public static function eventLoop() {
    return self::$instance->eventLoop;
  }

  public function __construct() {
    $failed = false;
    try {
      self::$instance = $this;
      spl_autoload_register([self::class, 'load']);
      $this->init();
      $this->initialized = true;
      $this->dispatchLifecycleEvent('init');
      $this->eventLoop->start();
    } catch (Throwable $error) {
      fwrite(STDERR, $error->getMessage() . "\n");
      $failed = true;
    } finally {
      try {
        if ($this->initialized) {
          $this->dispatchLifecycleEvent('close');
        }
      } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        $failed = true;
      } finally {
        $this->close();
      }
    }
    if ($failed) {
      exit(1);
    }
  }

  private function init() {
    $xmlParser = new XmlParser\XmlParser;
    $this->events = $xmlParser->events;
    $this->openSdl();
    $this->eventLoop = new Events\EventLoop;
    foreach ($this->events as $event) {
      if ($event->type === 'timer') {
        $this->eventLoop->addTimer($event->action, $event->period);
      }
    }
    $this->openFont($xmlParser->fontName, $xmlParser->fontSize);
    foreach ($xmlParser->windows as $windowData) {
      $window = new Core\Window($windowData);
      $this->eventLoop->registerWindow($window);
    }
  }

  private function close() {
    $this->eventLoop?->closeWindows();
    if ($this->font !== null) {
      $this->font->close();
    }
    if ($this->ttf !== null) {
      $this->ttf->close();
    }
    if ($this->sdl !== null) {
      $this->sdl->close();
    }
  }

  /** Dispatch an app lifecycle event to its declared actions. */
  private function dispatchLifecycleEvent(string $type): void {
    $dispatcher = new Events\EventDispatcher();
    $dispatcher->dispatch($this->events, new Events\EventContext($type), false);
  }

  private function openSdl() {
    $this->sdl = new SDLWrapper\SDL();
    $sdlReady = $this->sdl->ffi->SDL_Init(SDLWrapper\SDL::SDL_INIT_VIDEO);
    if (!$sdlReady) {
      throw new \RuntimeException('SDL initialization failed: ' . $this->sdl->error());
    }
  }

  private function openFont($name, $size) {
    $this->ttf = new SDLWrapper\TTF();
    $ttfReady = $this->ttf->ffi->TTF_Init();
    if (!$ttfReady) {
      throw new \RuntimeException('TTF initialization failed: ' . $this->sdl->error());
    }
    $this->font = new Rendering\Font($this->ttf);
    $this->font->open($name, $size);
  }

}
