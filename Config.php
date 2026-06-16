<?php

namespace SPTK;

class Config {

  private static $path = false;
  private static $home = false;

  private static function setPath(): void {
    $appName = basename(APP_PATH, '.php');
    self::$home = getenv('HOME') ?: getenv('USERPROFILE');
    if (!self::$home) {
      self::$home = getcwd();
    }
    $home = realpath(self::$home) ?: self::$home;
    $configHome = getenv('XDG_CONFIG_HOME');
    if ($configHome === false || $configHome === '') {
      $configHome = rtrim($home, '/') . '/.config';
    }
    self::$path = rtrim($configHome, '/') . "/{$appName}";
    if (!is_dir(self::$path)) {
      mkdir(self::$path, 0700, true);
    }
  }

  public static function getHome(): string {
    if (self::$home === false) {
      self::setPath();
    }
    return self::$home;
  }

  public static function getPath(): string {
    if (self::$path === false) {
      self::setPath();
    }
    return self::$path;
  }

  public static function getFilePath($name): string {
    if (self::$path === false) {
      self::setPath();
    }
    return self::$path . '/' . $name;
  }

  public static function load(string $file): array {
    if (!file_exists($file)) {
      return [];
    }
    $json = file_get_contents($file);
    if ($json === false) {
      return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
  }

  public static function save(string $file, array $data, ?string $rootName = null): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
      return false;
    }
    if ($rootName !== null) {
      $data = [$rootName => $data];
    }
    $json = json_encode((object)$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      return false;
    }
    return file_put_contents($file, $json . "\n", LOCK_EX) !== false;
  }

}
