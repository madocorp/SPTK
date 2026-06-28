<?php

define('SPTK\DEBUG', false);
define('DEBUG', false);
define('APP_PATH', dirname(__DIR__) . '/run.php');
define('APP_NAMESPACE', 'EXAMPLES');
define('SPTK_PATH', dirname(__DIR__));

chdir(SPTK_PATH);

require_once SPTK_PATH . '/Autoload.php';
require_once __DIR__ . '/Support.php';

$files = [
  __DIR__ . '/XssTest.php',
  __DIR__ . '/TemplateTest.php',
  __DIR__ . '/ElementTest.php',
  __DIR__ . '/TableTest.php',
];

$tests = [];
foreach ($files as $file) {
  $tests += require $file;
}

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
  try {
    $test();
    $passed++;
    echo ". {$name}\n";
  } catch (Throwable $e) {
    $failed++;
    echo "F {$name}\n";
    echo "  " . str_replace("\n", "\n  ", $e->getMessage()) . "\n";
  }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
