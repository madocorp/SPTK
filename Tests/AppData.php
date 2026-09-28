<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\AppData;

/** Assert a named configuration result. */
function expectAppData(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

$appName = 'sptk-config-test-' . bin2hex(random_bytes(8));
$directory = AppData::path($appName);
$defaultDirectory = dirname($directory) . '/.' . basename(APP_DIR);
$defaultExisted = is_dir($defaultDirectory);
try {
  expectAppData(AppData::path(), $defaultDirectory, 'default name follows APP_DIR');
  expectAppData(AppData::path($appName), $directory, 'existing data directory is reused');
  expectAppData(AppData::path(' ..' . $appName . ' '), $directory, 'name trims whitespace and leading dots');
  expectAppData(AppData::file('/config.json', $appName), $directory . '/config.json', 'file path joins directory');
  if (PHP_OS_FAMILY !== 'Windows') {
    expectAppData(fileperms($directory) & 0777, 0700, 'new data directory has private permissions');
  }
  expectAppData(AppData::loadJson('missing.json', $appName), [], 'missing config uses empty defaults');
  $config = ['theme' => 'dark', 'label' => 'Árvíztűrő', 'path' => '/tmp/example', 'nested' => ['enabled' => true]];
  expectAppData(AppData::saveJson('config.json', $config, $appName), true, 'save configuration');
  expectAppData(AppData::loadJson('config.json', $appName), $config, 'configuration round trip');
  $json = file_get_contents($directory . '/config.json');
  expectAppData(str_contains($json, 'Árvíztűrő'), true, 'Unicode stays readable');
  expectAppData(str_contains($json, '/tmp/example'), true, 'slashes stay readable');
  expectAppData(str_ends_with($json, "\n"), true, 'saved JSON ends with newline');
  expectAppData(AppData::saveJson('config.json', ['invalid' => NAN], $appName), false, 'invalid JSON data fails');
  expectAppData(AppData::loadJson('config.json', $appName), $config, 'encoding failure preserves config');
  file_put_contents($directory . '/config.json', '{invalid');
  expectAppData(AppData::loadJson('config.json', $appName), [], 'malformed config uses empty defaults');
  file_put_contents($directory . '/config.json', '42');
  expectAppData(AppData::loadJson('config.json', $appName), [], 'scalar config uses empty defaults');
  file_put_contents($directory . '/config.json', '["first", "second"]');
  expectAppData(AppData::loadJson('config.json', $appName), ['first', 'second'], 'JSON lists are supported');
} finally {
  if (is_file($directory . '/config.json')) {
    unlink($directory . '/config.json');
  }
  rmdir($directory);
  if (!$defaultExisted && is_dir($defaultDirectory)) {
    rmdir($defaultDirectory);
  }
}
echo "AppData checks passed\n";
