<?php

class Controller {

  public static function test() {
    echo "test callback\n";
    return true;
  }

  public static function testTop() {
    echo "testTop callback\n";
    return true;
  }

  public static function init() {
    echo "init callback\n";
    return true;
  }

}