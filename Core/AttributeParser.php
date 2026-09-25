<?php

namespace SPTK\Core;

/** Shared XML reading and validation; contains no widget or layout decisions. */
trait AttributeParser {

  public function attrSize(\XMLReader $reader, string $name, string $default = '1*'): string {
    $value = $reader->getAttribute($name) ?? $default;
// validate as size, integer - fixed size, * - weighted
    return $value;
  }

  public function attrInteger(\XMLReader $reader, string $name, int $default = 0): int {
    $value = $reader->getAttribute($name) ?? $default;
    return $value;
  }

  public function attrString(\XMLReader $reader, string $name, string $default = ''): string {
    $value = $reader->getAttribute($name) ?? $default;
    return $value;
  }

  public function attrEnum(\XMLReader $reader, string $name, array $expectedValues): string {
    $value = $reader->getAttribute($name) ?? $expectedValues[0] ?? '';
    return $value;
  }

  protected function attrColor(\XMLReader $reader, string $name, Color $default): Color {
    $value = $reader->getAttribute($name) ?? $default;
    if ($value === null) {
      return $default;
    }
    return Color::from($value);
  }

  protected function attrBoolean(\XMLReader $reader, string $name, bool $default = false): bool {
    $value = $reader->getAttribute($name) ?? $default;
    if ($value === null) {
      return $default;
    }
    return match (strtolower($value)) {
      '1', 'true', 'yes', 'on' => true,
      '0', 'false', 'no', 'off' => false,
      default => throw new \RuntimeException("<{$xml->getName()}> attribute '{$name}' must be boolean."),
    };
  }

}
