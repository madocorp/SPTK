<?php

namespace SPTK;

/**
 * Registers and resolves SPTK and app namespace classes from the project directory.
 */
class Autoload {

  private static $appDir;
  private static $appNamespace;

  public static function init(): void {
    self::$appNamespace = defined('APP_NAMESPACE') ? APP_NAMESPACE : '';
    self::$appDir = defined('APP_PATH') ? dirname(APP_PATH) : dirname(__DIR__);
    if (!defined('SPTK_PATH')) {
      define('SPTK_PATH', __DIR__);
    }
    spl_autoload_register([self::class, 'load']);
  }

  public static function load(string $class): void {
    $path = self::getPath($class);
    if (file_exists($path)) {
      require_once $path;
    }
  }

  public static function getPath(string $class): string {
    if (strpos($class, 'SPTK\\') === 0 || strpos($class, '\\SPTK\\') === 0) {
      $class = ltrim($class, '\\');
      $class = substr($class, strlen('SPTK\\'));
      $path = trim(str_replace('\\', '/', $class), '/') . '.php';
      return SPTK_PATH . '/' . $path;
    }
    $namespace = self::$appNamespace === '' ? $class : str_replace(self::$appNamespace . '\\', '', $class);
    return self::$appDir . '/' . trim(str_replace('\\', '/', $namespace), '/') . '.php';
  }

}

Autoload::init();
