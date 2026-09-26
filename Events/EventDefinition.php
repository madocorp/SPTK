<?php

namespace SPTK\Events;

/** Defines one XML event subscription and its static controller action. */
final class EventDefinition {

  /** Create a parsed event subscription. */
  public function __construct(
    public readonly string $type,
    public readonly ?string $key,
    public readonly string $action,
    public readonly ?int $period = null,
  ) {
  }

  /** Check whether a native key event matches this subscription. */
  public function matches(mixed $input): bool {
    if ($input === null || $this->key === null) {
      return $this->key === null;
    }
    if ($input->type !== \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN && $input->type !== \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_UP) {
      return false;
    }
    [$modifiers, $expectedKey] = self::keyParts($this->key);
    $nativeModifiers = (int)$input->key->mod;
    $actualModifiers = KeyNormalizer::normalizeModifiers($nativeModifiers);
    if ($actualModifiers !== $modifiers) {
      return false;
    }
    return KeyNormalizer::keyName((int)$input->key->key, $nativeModifiers) === $expectedKey;
  }

  /** Convert a normalized key chord into its modifier mask and key name. */
  private static function keyParts(string $key): array {
    $parts = explode('+', $key);
    $name = array_pop($parts);
    $modifiers = 0;
    foreach ($parts as $part) {
      $modifiers |= KeyNormalizer::modifierMask($part);
    }
    return [$modifiers, $name];
  }

}
