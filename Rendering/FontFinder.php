<?php

namespace SPTK\Rendering;

/** Resolves a font name using the original SdlFont lookup order. */
final class FontFinder {

  public static function find(string $name): string {
    if (trim($name) === '') {
      throw new \InvalidArgumentException('Font name must not be empty.');
    }
    if (is_file($name)) {
      return $name;
    }
    // Missing explicit paths must not silently select an unrelated installed font.
    if (str_contains($name, '/') || preg_match('/\.(ttf|otf|ttc|otc)$/iD', $name)) {
      throw new \RuntimeException("Font file not found: {$name}");
    }
    $normalized = self::normalize($name);
    $directories = ['/usr/share/fonts', '/usr/local/share/fonts'];
    $home = getenv('HOME');
    if ($home !== false && $home !== '') {
      $directories[] = $home . '/.local/share/fonts';
      $directories[] = $home . '/.fonts';
    }
    $best = null;
    $bestDistance = PHP_INT_MAX;
    foreach ($directories as $directory) {
      if (!is_dir($directory) || !is_readable($directory)) {
        continue;
      }
      $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        flags: \RecursiveIteratorIterator::CATCH_GET_CHILD,
      );
      foreach ($files as $file) {
        if (!$file->isFile() || !$file->isReadable() || !preg_match('/\.(ttf|otf|ttc|otc)$/iD', $file->getFilename())) {
          continue;
        }
        $candidate = self::normalize(pathinfo($file->getFilename(), PATHINFO_FILENAME));
        if ($candidate === $normalized) {
          return $file->getPathname();
        }
        $distance = levenshtein($normalized, $candidate);
        if ($distance < $bestDistance) {
          $best = $file->getPathname();
          $bestDistance = $distance;
        }
      }
    }
    $matched = self::fontConfigPath($name);
    if ($matched !== null) {
      return $matched;
    }
    if ($best === null) {
      throw new \RuntimeException("Unable to find a usable font for: {$name}");
    }
    return $best;
  }

  private static function normalize(string $name): string {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
  }

  private static function fontConfigPath(string $name): ?string {
    if (!is_callable('shell_exec')) {
      return null;
    }
    $command = 'fc-match -f ' . escapeshellarg('%{file}') . ' -- ' . escapeshellarg($name) . ' 2>/dev/null';
    $path = trim((string) shell_exec($command));
    return $path !== '' && is_file($path) ? $path : null;
  }

}
