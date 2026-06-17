<?php

namespace SPTK;

use \SPTK\SDLWrapper\SDL;
use \SPTK\SDLWrapper\TTF;

class App {

  public static $instance;

  private $xml;
  private $xss;
  private $dir;
  private $initCallback;
  private $loopCallback;
  private $timerCallback;
  private $endCallback;

  public function __construct(string $xml, string $xss, ?callable $init = null, ?callable $loop = null, ?callable $timer = null, ?callable $end = null) {
    $this->xml = $xml;
    $this->xss = $xss;
    $this->dir = dirname(APP_PATH);
    $this->initCallback = $init;
    $this->loopCallback = $loop;
    $this->timerCallback = $timer;
    $this->endCallback = $end;
    if (!is_null(self::$instance)) {
      throw new \Exception("SPTK\\App is a singleton, you can't instantiate more than once");
    }
    self::$instance = $this;
    new SDL([$this, 'init']);
  }

  public function init(SDL $sdl): void {
    if (!defined('DEBUG')) {
      define('DEBUG', false);
    }
    new TTF;
    Texture::init();
    $this->loadXss();
    $this->loadXml();
    $sdl->setEventCallback([\SPTK\Element::$root, 'eventHandler']);
    $sdl->setLoopCallback($this->loopCallback);
    $sdl->setTimerCallback($this->timerCallback);
    $sdl->setEndCallback([$this, 'end']);
    if ($this->initCallback !== null) {
      call_user_func($this->initCallback);
    }
  }

  public function loadXml(): void {
    new LayoutXmlReader($this->xml);
  }

  public function loadXss(): void {
    if (!is_array($this->xss)) {
      $this->xss = [$this->xss];
    }
    array_unshift($this->xss, SPTK_PATH . "/defaults.xss");
    foreach ($this->xss as $xssi) {
      StyleSheet::load($xssi);
    }
  }

  public function end(): void {
    if ($this->endCallback !== null) {
      call_user_func($this->endCallback);
    }
    Font::closeAll();
  }

  public function getDir(): string {
    return $this->dir;
  }

  public function quit(): void {
    SDL::$instance->end();
  }

}
