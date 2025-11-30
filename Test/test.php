<?php

require_once '../App.php';

define('SPTK\DEBUG', true);

class Controller {

  public static function tab($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\KeyCode::TAB) {
        $next = $element->getNext();
        $next->raise();
        SPTK\Element::refresh();
      }
    }
  }

  public static function alterClass($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\KeyCode::TAB) {
        $dynamicClassBox = SPTK\Element::getById('dynamic-class-box');
        if ($dynamicClassBox->hasClass('yellow')) {
          $dynamicClassBox->removeClass('yellow');
          $dynamicClassBox->addClass('red');
        } else {
          $dynamicClassBox->removeClass('red');
          $dynamicClassBox->addClass('yellow');
        }
        $dynamicClassBox->redraw();
        SPTK\Element::refresh();
      }
    }
  }

  public static function showPanel($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\KeyCode::SPACE) {
        $telement = SPTK\Element::getById('panel');
        $telement->show();
        SPTK\Element::refresh();
      }
    }
  }

  public static function noop() {
    return false;
  }

}

if (isset($argv[1])) {
  $test = $argv[1];
  echo "Test: {$test}\n";
  new SPTK\App("{$test}/layout.xml", "{$test}/style.xss");
} else {
  $dir = new DirectoryIterator(dirname(__FILE__));
  foreach ($dir as $fileinfo) {
    if ($fileinfo->isDir() && !$fileinfo->isDot()) {
      $test = $fileinfo->getFilename();
      passthru("php test.php {$test}");
    }
  }
}
