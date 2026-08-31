<?php

namespace SPTK\Core;

/**
 * Describes normalized input delivered to elements by the focus manager.
 */
class InputEvent {

  public function __construct(
    public string $type,
    public string $key = '',
    public string $text = '',
    public array $modifiers = []
  ) {
  }

  public static function key(string $key, array $modifiers = []): self {
    return new self('key', $key, '', $modifiers);
  }

  public static function keyRelease(string $key, array $modifiers = []): self {
    return new self('key-release', $key, '', $modifiers);
  }

  public static function text(string $text): self {
    return new self('text', '', $text);
  }

}
