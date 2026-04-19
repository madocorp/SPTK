<?php

define('SPTK\DEBUG', true);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'TEST');

require_once 'SPTK/Autoload.php';
require_once 'Editor/SqlTokenizer.php';


class Controller {

  public static function alterClass($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SdlWrapper\KeyCode::TAB) {
        $dynamicClassBox = SPTK\Element::byName('dynamic-class-box');
        if ($dynamicClassBox->hasClass('yellow')) {
          $dynamicClassBox->removeClass('yellow');
          $dynamicClassBox->addClass('red');
        } else {
          $dynamicClassBox->removeClass('red');
          $dynamicClassBox->addClass('yellow');
        }
        SPTK\Element::refresh();
      }
    }
  }

  public static function showPanel($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SdlWrapper\KeyCode::SPACE) {
        $telement = SPTK\Element::byName('panel');
        $telement->show();
        SPTK\Element::refresh();
      }
    }
    return true;
  }

  public static function panelForge($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SdlWrapper\KeyCode::SPACE) {
        SPTK\WarningPanel::forge('forged warning', 'test');
      }
    }
    return true;
  }

  public static function noop() {
    return false;
  }

  public static function refresh() {
    SPTK\Element::refresh();
    SPTK\Element::$root->debug();
    return false;
  }

}

if (isset($argv[1])) {
  $test = $argv[1];
  echo "Test: {$test}\n";
  new SPTK\App("{$test}/layout.xml", "{$test}/style.xss");
}
