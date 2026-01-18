<?php

namespace SPTK;

require_once 'SDLWrapper/SDL.php';
require_once 'SDLWrapper/TTF.php';
require_once 'SDLWrapper/KeyCode.php';
require_once 'SDLWrapper/KeyModifier.php';
require_once 'SDLWrapper/ScanCode.php';
require_once 'SDLWrapper/Action.php';
require_once 'SDLWrapper/KeyCombo.php';
require_once 'Config.php';
require_once 'Font.php';
require_once 'Texture.php';
require_once 'Geometry.php';
require_once 'ElementStatic.php';
require_once 'ElementAssistant.php';
require_once 'ElementLayout.php';
require_once 'ElementTree.php';
require_once 'ElementStyle.php';
require_once 'ElementEvent.php';
require_once 'Element.php';
require_once 'LayoutXmlReader.php';
require_once 'Style.php';
require_once 'StyleSheet.php';
require_once 'Border.php';
require_once 'Scrollbar.php';
require_once 'Clipboard.php';
require_once 'Tokenizer.php';

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
    spl_autoload_register([$this, 'autoload']);
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

  public function autoload($class) {
    $class = explode('\\', $class, 2);
    if ($class[0] == 'SPTK') {
      $class[1] = str_replace('\\', '/', $class[1]);
      $path = __DIR__ . "/Elements/{$class[1]}.php";
      if (DEBUG) {
        echo "AUTOLOAD: $path\n";
      }
      if (file_exists($path)) {
        require_once $path;
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
