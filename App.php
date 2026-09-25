<?php

namespace SPTK;

final class App {

  private static $instance;
  private $eventLoop;
  private $sdl;
  private $ttf;
  private $font;
  private $gridRenderer;

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
    return self::$instance->sdl;
  }

  public static function ttf() {
    return self::$instance->ttf;
  }

  public static function gridRenderer() {
    return self::$instance->gridRenderer;
  }

  public static function font() {
    return self::$instance->font;
  }

  public static function eventLoop() {
    return self::$instance->eventLoop;
  }

  public function __construct() {
    try {
      self::$instance = $this;
      spl_autoload_register([self::class, 'load']);
      $this->init();
      $this->eventLoop->start();
    } catch (Throwable $error) {
      fwrite(STDERR, $error->getMessage() . "\n");
      exit(1);
    }
  }

  private function init() {
    $xmlParser = new Core\XmlParser;
    $this->openSdl();
    $this->eventLoop = new Core\EventLoop;
    $this->openFont($xmlParser->fontName, $xmlParser->fontSize);
    $this->gridRenderer = new Rendering\GridRenderer();
    foreach ($xmlParser->windows as $windowData) {
      $window = new Core\Window($windowData);
      $this->eventLoop->registerWindow($window);
    }
  }

  private function close() {
    $this->gridRenderer->close();
    foreach ($this->windows as $window) {
      $window->close();
    }
    $this->font->close();
    $this->ttf->close();
    $this->sdl->close();
  }

  private function openSdl() {
    $this->sdl = new SDLWrapper\SDL();
    $sdlReady = $this->sdl->ffi->SDL_Init(SDLWrapper\SDL::SDL_INIT_VIDEO);
    if (!$sdlReady) {
      throw new \RuntimeException('SDL initialization failed: ' . $this->sdl->error());
    }
  }

  private function openFont($name, $size) {
    $ttf = new SDLWrapper\TTF();
    $ttfReady = $ttf->ffi->TTF_Init();
    if (!$ttfReady) {
      throw new \RuntimeException('TTF initialization failed: ' . $this->sdl->error());
    }
    $this->font = new Rendering\Font($ttf);
    $this->font->open($name, $size);
  }

}
