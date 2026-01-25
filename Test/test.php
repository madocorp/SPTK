<?php

require_once '../App.php';
require_once 'Editor/SqlTokenizer.php';

define('SPTK\DEBUG', true);

class Controller {

  public static function alterClass($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\KeyCode::TAB) {
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
      if ($event['key'] == SPTK\KeyCode::SPACE) {
        $telement = SPTK\Element::byName('panel');
        $telement->show();
        SPTK\Element::refresh();
      }
    }
    return true;
  }

  public static function panelForge($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\KeyCode::SPACE) {
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
