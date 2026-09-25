<?php

namespace SPTK\Core;

/** Parses and validates event declarations from XML. */
final class EventParser {

  use AttributeParser;

  private const TYPES = ['keyDown', 'keyUp', 'textInput', 'select', 'unselect', 'activate', 'deactivate', 'accept', 'cancel', 'change'];

  /** Parse the current Event element and leave the reader on its closing element. */
  public function parse(\XMLReader $reader): EventDefinition {
    $this->assertAttributes($reader, ['type', 'key', 'action']);
    $type = $this->attrString($reader, 'type');
    if (!in_array($type, self::TYPES, true)) {
      throw new \RuntimeException("Unknown event type: {$type}");
    }
    $key = $reader->getAttribute('key');
    if ($key !== null && !in_array($type, ['keyDown', 'keyUp'], true)) {
      throw new \RuntimeException("<Event key> is only valid for keyDown and keyUp.");
    }
    $key = $key === null ? null : $this->normalizeKey($key);
    $action = $this->attrString($reader, 'action');
    if (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $action)) {
      throw new \RuntimeException("Event action must name a static method as Class::method.");
    }
    $this->consumeEmptyElement($reader);
    return new EventDefinition($type, $key, $action);
  }

  /** Normalize a key chord and reject unsupported key or modifier names. */
  private function normalizeKey(string $key): string {
    $parts = array_map('strtolower', explode('+', trim($key)));
    $name = array_pop($parts);
    $modifiers = [];
    foreach ($parts as $modifier) {
      if (!in_array($modifier, ['ctrl', 'shift', 'alt'], true) || in_array($modifier, $modifiers, true)) {
        throw new \RuntimeException("Invalid key modifier in '{$key}'.");
      }
      $modifiers[] = $modifier;
    }
    sort($modifiers);
    $validKeys = ['enter', 'return', 'escape', 'esc', 'backspace', 'tab', 'space', 'delete', 'up', 'down', 'left', 'right', 'home', 'end', 'pageup', 'pagedown', 'insert'];
    if (!in_array($name, $validKeys, true) && !preg_match('/^(?:[a-z0-9]|f(?:[1-9]|1[0-2]))$/', $name)) {
      throw new \RuntimeException("Invalid key name in '{$key}'.");
    }
    $name = match ($name) {
      'return' => 'enter',
      'esc' => 'escape',
      default => $name,
    };
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
