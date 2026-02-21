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

  public function __construct($xml, $xss, $init = false, $loop = false, $timer = false, $end = false) {
    $this->xml = $xml;
    $this->xss = $xss;
    $this->dir = dirname(__FILE__);
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

  public function init($sdl) {
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
    if (DEBUG) {
      Element::$root->debug();
    }
    if ($this->initCallback !== false) {
      call_user_func($this->initCallback);
    }
  }

  public function loadXml() {
    new LayoutXmlReader($this->xml);
  }

  public function loadXss() {
    if (!is_array($this->xss)) {
      $this->xss = [$this->xss];
    }
    array_unshift($this->xss, __DIR__ . '/defaults.xss');
    foreach ($this->xss as $xssi) {
      StyleSheet::load($xssi);
    }
  }

  public function end() {
    if ($this->endCallback !== false) {
      call_user_func($this->endCallback);
    }
    Font::closeAll();
  }

  public function getDir() {
    return $this->dir;
  }

  public function quit() {
    SDL::$instance->end();
  }

}
