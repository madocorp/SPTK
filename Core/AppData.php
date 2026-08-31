<?php

namespace SPTK\Core;

/**
 * Resolves and stores private per-application data files.
 */
class AppData {

  public static function path(?string $appName = null): string {
    $path = self::home() . '/.' . self::appName($appName);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
      throw new \RuntimeException("Could not create app data directory: {$path}");
    }
    return $path;
  }

  public static function file(string $name, ?string $appName = null): string {
    return self::path($appName) . '/' . ltrim($name, '/');
  }

  public static function loadJson(string $name, ?string $appName = null): array {
    $file = self::file($name, $appName);
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

  public static function saveJson(string $name, array $data, ?string $appName = null): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      return false;
    }
    return file_put_contents(self::file($name, $appName), $json . "\n", LOCK_EX) !== false;
  }

  private static function home(): string {
    $home = getenv('HOME') ?: getenv('USERPROFILE') ?: getcwd();
    return realpath($home) ?: $home;
  }

  private static function appName(?string $appName = null): string {
    $appName ??= defined('APP_PATH') ? basename(APP_PATH, '.php') : 'sptk';
    $appName = trim($appName);
    if ($appName === '') {
      return 'sptk';
    }
    $appName = ltrim(str_replace(['/', '\\'], '-', $appName), '.');
    return $appName === '' ? 'sptk' : $appName;
  }

}
