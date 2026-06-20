<?php

namespace SPTK;

class Autoload {

  private static $appDir;
  private static $appNamespace;

  public static function init(): void {
    self::$appNamespace = APP_NAMESPACE;
    self::$appDir = dirname(APP_PATH);
    if (!defined('SPTK_PATH')) {
      define('SPTK_PATH', self::$appDir . "/SPTK");
    }
    if (DEBUG !== false) {
      require_once SPTK_PATH . "/DebugStream.php";
      stream_wrapper_register('debug', DebugStream::class);
    }
    spl_autoload_register(['\SPTK\Autoload', 'load']);
  }

  public static function load(string $class): void {
    $path = self::getPath($class);
    if (DEBUG !== false) {
      if (DEBUG === true || in_array(DEBUG, 'autoload')) {
        echo "AUTOLOAD: $path\n";
      }
      require_once "debug://{$path}";
    } else {
      require_once $path;
    }
  }

  public static function exists(string $class): bool {
    $path = self::getPath($class);
    return file_exists($path);
  }

  public static function getPath(string $class): string {
    if (strpos($class, 'SPTK\\') === 0 || strpos($class, '\\SPTK\\') === 0) {
      $class = ltrim($class, '\\');
      $class = substr($class, strlen('SPTK\\'));
      $path = trim(str_replace('\\', '/', $class), '/') . '.php';
      return SPTK_PATH . '/' . $path;
    }
    $namespace = str_replace(self::$appNamespace . '\\', '', $class);
    return self::$appDir . '/' . trim(str_replace('\\', '/', $namespace), '/') . '.php';
  }


}

Autoload::init();
