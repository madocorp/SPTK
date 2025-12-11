<?php

namespace SPTK;

require_once 'SDLWrapper/SDL.php';
require_once 'SDLWrapper/TTF.php';
require_once 'SDLWrapper/KeyCode.php';
require_once 'Font.php';
require_once 'Texture.php';
require_once 'Geometry.php';
require_once 'Cursor.php';
require_once 'Element.php';
require_once 'LayoutXmlReader.php';
require_once 'Style.php';
require_once 'StyleSheet.php';
require_once 'Border.php';

class App {

  public static $instance;

  private $xml;
  private $xss;
  private $dir;
  private $initCallback;
  private $timerCallback;
  private $endCallback;

  public function __construct($xml, $xss, $init = null, $timer = null, $end = null) {
    $this->xml = $xml;
    $this->xss = $xss;
    $this->dir = dirname(__FILE__);
    $this->initCallback = $init;
    $this->timerCallback = $timer;
    $this->endCallback = $end;
    if (!is_null(self::$instance)) {
      throw new \Exception("SPTK\\App is a singleton, you can't instantiate more than once");
    }
    self::$instance = $this;
    spl_autoload_register([$this, 'autoload']);
    new SDL([$this, 'init'], [$this, 'timer'], [$this, 'eventHandler'], [$this, 'end']);
  }

  public function init() {
    if (!defined('DEBUG')) {
      define('DEBUG', false);
    }
    new TTF;
    $this->loadXss();
    $this->loadXml();
    if (DEBUG) {
      Element::$root->debug();
    }
    Element::refresh();
    if (!is_null($this->initCallback)) {
      call_user_func($this->initCallback);
    }
  }

  public function autoload($class) {
    $class = explode('\\', $class);
    if ($class[0] == 'SPTK') {
      if (file_exists(__DIR__ . "/Elements/{$class[1]}.php")) {
        require_once "Elements/{$class[1]}.php";
      }
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

  public function eventHandler($event) {
    switch ($event['type']) {
      case SDL::SDL_EVENT_WINDOW_EXPOSED:
        Element::refresh();
        break;
      case SDL::SDL_EVENT_WINDOW_RESIZED:
        Element::refresh();
        break;
    }
    Element::event($event);
  }

  public function timer() {
    if (DEBUG) {
//      echo "timer\n";
    }
    if (!is_null($this->timerCallback)) {
      call_user_func($this->timerCallback);
    }
  }

  public function end() {
    if (!is_null($this->endCallback)) {
      call_user_func($this->endCallback);
    }
    Font::closeAll();
  }

  public function getDir() {
    return $this->dir;
  }

}
