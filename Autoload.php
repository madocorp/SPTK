<?php

namespace SPTK;

class Autoload {

  private static $appDir;
  private static $appNamespace;

  public static function init() {
    self::$appNamespace = APP_NAMESPACE;
    self::$appDir = dirname(APP_PATH);
    spl_autoload_register(['\SPTK\Autoload', 'autoload']);
  }

  public static function autoload($class) {
    $path = self::getPath($class);
    if (DEBUG) {
      echo "AUTOLOAD: $path\n";
    }
    require_once $path;
  }

  public static function exists($class) {
    $path = self::getPath($class);
    return file_exists($path);
  }

  public static function getPath($class) {
    $namespace = str_replace(self::$appNamespace . '\\', '', $class);
    return self::$appDir . '/' . trim(str_replace('\\', '/', $namespace), '/') . '.php';
  }

}

Autoload::init();