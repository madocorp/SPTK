<?php

define('SPTK\DEBUG', true);
define('DEBUG', true);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'EXAMPLES');

require_once __DIR__ . '/SPTK/Autoload.php';

class Controller {

  public static function alterClass($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SDLWrapper\KeyCode::TAB) {
        $dynamicClassBox = SPTK\Element::byName('dynamic-class-box');
        if ($dynamicClassBox->hasClass('yellow')) {
          $dynamicClassBox->removeClass('yellow');
          $dynamicClassBox->addClass('red');
        } else {
          $dynamicClassBox->removeClass('red');
          $dynamicClassBox->addClass('yellow');
        }
        SPTK\Element::refresh();
      }
    }
  }

  public static function showPanel($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SDLWrapper\KeyCode::SPACE) {
        $telement = SPTK\Element::byName('panel');
        $telement->show();
        SPTK\Element::refresh();
      }
    }
    return true;
  }

  public static function panelForge($element, $event) {
    if ($event['name'] == 'KeyPress') {
      if ($event['key'] == SPTK\SDLWrapper\KeyCode::SPACE) {
        SPTK\Elements\WarningPanel::forge('forged warning', 'test');
      }
    }
    return true;
  }

  public static function noop() {
    return false;
  }

  public static function refresh() {
    SPTK\Element::refresh();
    SPTK\Element::$root->debug();
    return false;
  }

}

function examplePaths(): array {
  $paths = glob(__DIR__ . '/Interactive/*/*/layout.xml');
  sort($paths);
  return array_map(
    fn($path) => substr(dirname($path), strlen(__DIR__) + 1),
    $paths
  );
}

function printUsage(): void {
  echo "Usage: php Examples/run.php <example>\n\n";
  echo "Examples:\n";
  foreach (examplePaths() as $example) {
    echo "  {$example}\n";
  }
}

if (!isset($argv[1])) {
  printUsage();
  exit(0);
}

$example = trim($argv[1], '/');
$exampleDir = __DIR__ . '/' . $example;
if (!is_dir($exampleDir)) {
  $matches = array_values(array_filter(
    examplePaths(),
    fn($path) => basename($path) === $example || $path === $example
  ));
  if (count($matches) === 1) {
    $example = $matches[0];
    $exampleDir = __DIR__ . '/' . $example;
  }
}

if (!is_file("{$exampleDir}/layout.xml") || !is_file("{$exampleDir}/style.xss")) {
  echo "Unknown example: {$argv[1]}\n\n";
  printUsage();
  exit(1);
}

echo "Example: {$example}\n";
if ($example === 'Interactive/Editor/Editor') {
  require_once __DIR__ . '/Interactive/Editor/Editor/SqlTokenizer.php';
}
new SPTK\App("{$exampleDir}/layout.xml", "{$exampleDir}/style.xss");
