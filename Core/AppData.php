<?php

namespace SPTK\Core;

/** Resolves private per-application data paths and reads or writes JSON configuration files. */
final class AppData {

  /** Return the application's data directory, creating it with private permissions when needed. */
  public static function path(?string $appName = null): string {
    $path = self::home() . '/.' . self::appName($appName);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
      throw new \RuntimeException("Could not create app data directory: {$path}");
    }
    if (!self::restrict($path, 0700)) {
      throw new \RuntimeException("Could not make app data directory private: {$path}");
    }
    return $path;
  }

  /** Resolve a file name inside the application's data directory. */
  public static function file(string $name, ?string $appName = null): string {
    return self::path($appName) . '/' . ltrim($name, '/');
  }

  /** Read a JSON array or object, returning an empty array for missing or invalid data. */
  public static function loadJson(string $name, ?string $appName = null): array {
    $file = self::file($name, $appName);
    if (!file_exists($file)) {
      return [];
    }
    if (!self::restrict($file, 0600)) {
      throw new \RuntimeException("Could not make app data file private: {$file}");
    }
    $json = file_get_contents($file);
    if ($json === false) {
      return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
  }

  /** Save readable JSON with private file permissions and an exclusive write lock. */
  public static function saveJson(string $name, array $data, ?string $appName = null): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      return false;
    }
    $file = self::file($name, $appName);
    if (is_file($file) && !self::restrict($file, 0600)) {
      return false;
    }
    return file_put_contents($file, $json . "\n", LOCK_EX) !== false
      && self::restrict($file, 0600);
  }

  /** Remove group and other access from existing Unix files and directories. */
  private static function restrict(string $path, int $permissions): bool {
    if (PHP_OS_FAMILY === 'Windows') {
      return true;
    }
    clearstatcache(true, $path);
    $mode = fileperms($path);
    return ($mode !== false && ($mode & 0077) === 0) || chmod($path, $permissions);
  }

  /** Resolve the user's home directory using the platform environment. */
  private static function home(): string {
    $homeDirectory = getenv('HOME') ?: getenv('USERPROFILE') ?: getcwd();
    return realpath($homeDirectory) ?: $homeDirectory;
  }

  /** Derive a directory name from APP_DIR or normalize an explicitly supplied application name. */
  private static function appName(?string $appName): string {
    $appName ??= basename(APP_DIR);
    $appName = trim($appName);
    $appName = ltrim(str_replace(['/', '\\'], '-', $appName), '.');
    return $appName === '' ? 'sptk' : $appName;
  }

}
