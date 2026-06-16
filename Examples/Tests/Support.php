<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\StyleSheet;

class TestFailure extends \Exception {
}

class HeadlessRoot extends Element {

  protected function init() {
    $this->geometry->width = 800;
    $this->geometry->height = 600;
    $this->geometry->innerWidth = 800;
    $this->geometry->innerHeight = 600;
    $this->geometry->fullWidth = 800;
    $this->geometry->fullHeight = 600;
    $this->geometry->windowWidth = 800;
    $this->geometry->windowHeight = 600;
  }

  public function raise() {
    ;
  }

  public function findAncestorByType($type) {
    return false;
  }

}

function resetToolkit(): void {
  Element::$root = null;

  $element = new \ReflectionClass(Element::class);
  $nextId = $element->getProperty('nextInternalId');
  $nextId->setAccessible(true);
  $nextId->setValue(0);

  $styleSheet = new \ReflectionClass(StyleSheet::class);
  foreach (['styles', 'cache'] as $propertyName) {
    $property = $styleSheet->getProperty($propertyName);
    $property->setAccessible(true);
    $property->setValue([]);
  }
  StyleSheet::load(__DIR__ . '/../../defaults.xss');
  StyleSheet::load(__DIR__ . '/headless.xss');
}

function root(): HeadlessRoot {
  resetToolkit();
  return new HeadlessRoot(null, false, false, 'Root');
}

function tempFile(string $contents, string $suffix): string {
  $path = tempnam(sys_get_temp_dir(), 'sptk-');
  if ($path === false) {
    throw new \RuntimeException('Unable to create temporary file.');
  }
  $target = "{$path}.{$suffix}";
  rename($path, $target);
  file_put_contents($target, $contents);
  return $target;
}

function assertTrue(bool $actual, string $message): void {
  if (!$actual) {
    throw new TestFailure($message);
  }
}

function assertFalse(bool $actual, string $message): void {
  assertTrue(!$actual, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
  if ($expected !== $actual) {
    throw new TestFailure($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
  }
}

function assertInstanceOf(string $class, mixed $actual, string $message): void {
  if (!$actual instanceof $class) {
    $type = is_object($actual) ? get_class($actual) : gettype($actual);
    throw new TestFailure("{$message}\nExpected instance of: {$class}\nActual: {$type}");
  }
}

function assertThrows(callable $callback, string $message): void {
  try {
    $callback();
  } catch (\Throwable $e) {
    return;
  }
  throw new TestFailure($message);
}

function propertyValue(object|string $target, string $propertyName): mixed {
  $class = new \ReflectionClass(is_object($target) ? get_class($target) : $target);
  while (!$class->hasProperty($propertyName)) {
    $class = $class->getParentClass();
    if ($class === false) {
      throw new \RuntimeException("Unknown property: {$propertyName}");
    }
  }
  $property = $class->getProperty($propertyName);
  $property->setAccessible(true);
  return $property->getValue(is_object($target) ? $target : null);
}
