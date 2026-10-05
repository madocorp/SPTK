<?php

define('APP_DIR', __DIR__);
define('APP_NAMESPACE', 'SPTKTEST');

require_once APP_DIR . '/SPTK/App.php';

if (!class_exists(\SPTK\Core\Color::class)) {
  throw new RuntimeException('SPTK classes must autoload after requiring App.php.');
}
if (!class_exists(\SPTKTEST\Fixtures\AutoloadProbe::class)) {
  throw new RuntimeException('Application classes must autoload from APP_DIR.');
}

echo "Autoload checks passed\n";
