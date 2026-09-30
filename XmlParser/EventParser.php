<?php

namespace SPTK\XmlParser;

use SPTK\Events\EventDefinition;
use SPTK\Events\KeyNormalizer;

/** Parses and validates event declarations from XML. */
final class EventParser {

  use AttributeParser;

  private const TYPES = ['keyDown', 'keyUp', 'textInput', 'select', 'unselect', 'activate', 'deactivate', 'accept', 'cancel', 'change', 'reorder', 'init', 'close', 'timer'];

  /** Parse the current Event element and leave the reader on its closing element. */
  public function parse(\XMLReader $reader): EventDefinition {
    $this->assertAttributes($reader, ['type', 'key', 'period', 'action']);
    $type = $this->attrString($reader, 'type');
    if (!in_array($type, self::TYPES, true)) {
      throw new \RuntimeException("Unknown event type: {$type}");
    }
    $key = $reader->getAttribute('key');
    if ($key !== null && !in_array($type, ['keyDown', 'keyUp'], true)) {
      throw new \RuntimeException("<Event key> is only valid for keyDown and keyUp.");
    }
    $key = $key === null ? null : $this->normalizeKey($key);
    $period = $reader->getAttribute('period');
    if ($type === 'timer') {
      if ($period === null || !ctype_digit($period) || (int)$period < 1) {
        throw new \RuntimeException('<Event type="timer"> requires a positive period in milliseconds.');
      }
      $period = (int)$period;
    } else if ($period !== null) {
      throw new \RuntimeException('<Event period> is only valid for timer events.');
    }
    $action = $this->attrString($reader, 'action');
    if (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $action)) {
      throw new \RuntimeException("Event action must name a static method as Class::method.");
    }
    $this->consumeEmptyElement($reader);
    return new EventDefinition($type, $key, $action, $period);
  }

  /** Normalize a key chord and reject unsupported key or modifier names. */
  private function normalizeKey(string $key): string {
    $parts = array_map('strtolower', explode('+', trim($key)));
    $name = array_pop($parts);
    $modifiers = [];
    foreach ($parts as $modifier) {
      $modifier = KeyNormalizer::normalizeModifierName($modifier);
      if ($modifier === null || in_array($modifier, $modifiers, true)) {
        throw new \RuntimeException("Invalid key modifier in '{$key}'.");
      }
      $modifiers[] = $modifier;
    }
    sort($modifiers);
    $name = KeyNormalizer::normalizeName($name);
    if ($name === null) {
      throw new \RuntimeException("Invalid key name in '{$key}'.");
    }
    return implode('+', array_merge($modifiers, [$name]));
  }

  /** Reject content inside Event and advance over its closing tag. */
  private function consumeEmptyElement(\XMLReader $reader): void {
    if ($reader->isEmptyElement) {
      return;
    }
    $name = $reader->name;
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === $name) {
        return;
      }
      if ($reader->nodeType === \XMLReader::ELEMENT || ($reader->nodeType === \XMLReader::TEXT && trim($reader->value) !== '')) {
        throw new \RuntimeException('<Event> must be empty.');
      }
    }
    throw new \RuntimeException('<Event> is not closed.');
  }

}
