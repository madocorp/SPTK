<?php

namespace SPTK\Tests;

/**
 * Signals an assertion failure in the lightweight SPTK test runner.
 */
class TestFailure extends \Exception {
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
  if ($expected !== $actual) {
    throw new TestFailure($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
  }
}

function assertTrue(bool $actual, string $message): void {
  if (!$actual) {
    throw new TestFailure($message);
  }
}

function assertInstanceOf(string $class, mixed $actual, string $message): void {
  if (!$actual instanceof $class) {
    $type = is_object($actual) ? get_class($actual) : gettype($actual);
    throw new TestFailure("{$message}\nExpected: {$class}\nActual: {$type}");
  }
}
