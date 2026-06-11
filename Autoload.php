<?php

namespace SPTK;

class Autoload {

  private static $appDir;
  private static $appNamespace;

  public static function init(): void {
    self::$appNamespace = APP_NAMESPACE;
    self::$appDir = dirname(APP_PATH);
    if (DEBUG !== false) {
      require_once self::$appDir . "/SPTK/DebugStream.php";
      stream_wrapper_register('debug', DebugStream::class);
    }
    spl_autoload_register(['\SPTK\Autoload', 'load']);
  }

  public static function load(string $class): void {
    $path = self::getPath($class);
    if (DEBUG !== false) {
      echo "AUTOLOAD: $path\n";
      require_once "debug://{$path}";
    } else {
      require_once self::$appDir . '/' . $path;
    }
  }

  public static function exists(string $class): bool {
    $path = self::getPath($class);
    return file_exists($path);
  }

  public static function getPath(string $class): string {
    $namespace = str_replace(self::$appNamespace . '\\', '', $class);
    return trim(str_replace('\\', '/', $namespace), '/') . '.php';
  }


}

Autoload::init();
