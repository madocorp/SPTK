<?php

namespace SPTK\Widgets\FileSelector;

/** Reads a directory into sorted, safely labeled list records. */
final class DirectoryListing {

  /** Resolve and read a directory before its caller changes the active path. */
  public static function read(string $path): array {
    clearstatcache();
    $resolved = realpath($path);
    $names = $resolved !== false && is_dir($resolved) && is_readable($resolved) ? @scandir($resolved) : false;
    if ($names === false) {
      throw new \RuntimeException("Cannot read directory: {$path}");
    }
    $directories = [];
    $files = [];
    foreach ($names as $name) {
      if ($name === '.' || $name === '..') {
        continue;
      }
      $full = rtrim($resolved, '/') . '/' . $name;
      $label = preg_replace('/[\p{Cc}]/u', ' ', mb_convert_encoding($name, 'UTF-8', 'UTF-8'));
      $directory = is_dir($full);
      $record = ['value' => bin2hex($full), 'label' => ($directory ? '/' : ' ') . $label, 'searchOffset' => 1];
      if ($directory) {
        $directories[] = $record;
      } else {
        $files[] = $record;
      }
    }
    usort($directories, self::compareItems(...));
    usort($files, self::compareItems(...));
    $parent = dirname($resolved);
    $parentItem = ['value' => bin2hex($parent), 'label' => $parent === $resolved ? '/' : '..'];
    $directoryIds = array_fill_keys([bin2hex($parent), ...array_column($directories, 'value')], true);
    return [$resolved, [$parentItem, ...$directories, ...$files], $directoryIds];
  }

  /** Sort names naturally without losing a stable tie-breaker. */
  private static function compareItems(array $a, array $b): int {
    return strnatcasecmp($a['label'], $b['label']) ?: strcmp($a['label'], $b['label']);
  }

}
