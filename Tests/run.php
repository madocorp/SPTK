<?php

define('SPTK_PATH', dirname(__DIR__));

require_once SPTK_PATH . '/Autoload.php';
require_once __DIR__ . '/Support.php';

$files = [
  __DIR__ . '/AppDataTest.php',
  __DIR__ . '/ColorTest.php',
  __DIR__ . '/GridBufferTest.php',
  __DIR__ . '/LayoutTest.php',
  __DIR__ . '/ElementTest.php',
  __DIR__ . '/TabViewTest.php',
  __DIR__ . '/CheckboxTest.php',
  __DIR__ . '/RadioButtonsTest.php',
  __DIR__ . '/InputActionTest.php',
  __DIR__ . '/InputTest.php',
  __DIR__ . '/ColorPickerTest.php',
  __DIR__ . '/DatePickerTest.php',
  __DIR__ . '/SelectorTest.php',
  __DIR__ . '/BrowserTest.php',
  __DIR__ . '/ProgressBarTest.php',
  __DIR__ . '/GraphViewTest.php',
  __DIR__ . '/GraphicsViewTest.php',
  __DIR__ . '/SdlAppTimerTest.php',
  __DIR__ . '/ImageViewTest.php',
  __DIR__ . '/StyledTextBoxTest.php',
  __DIR__ . '/ListViewTest.php',
  __DIR__ . '/TableViewTest.php',
  __DIR__ . '/TextEditorTest.php',
  __DIR__ . '/InputFocusTest.php',
  __DIR__ . '/InvalidationTest.php',
  __DIR__ . '/DialogPanelTest.php',
  __DIR__ . '/MenuBarTest.php',
  __DIR__ . '/MenuPopupTest.php',
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
  } catch (\Throwable $e) {
    $failed++;
    echo "F {$name}\n";
    echo "  " . str_replace("\n", "\n  ", $e->getMessage()) . "\n";
  }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
