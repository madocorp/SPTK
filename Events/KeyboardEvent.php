<?php

namespace SPTK\Events;

/** Copies an SDL keyboard event into PHP scalar values at the FFI boundary. */
final class KeyboardEvent {

  public readonly int $type;
  public readonly object $key;

  /** Preserve keyboard fields while converting native scalar wrappers. */
  public function __construct(mixed $native) {
    $this->type = (int)$native->type;
    $this->key = (object)[
      'windowID' => (int)$native->key->windowID,
      'which' => (int)$native->key->which,
      'scancode' => (int)$native->key->scancode,
      'key' => (int)$native->key->key,
      'mod' => (int)$native->key->mod,
      'raw' => (int)$native->key->raw,
      'down' => (bool)$native->key->down,
      'repeat' => (bool)$native->key->repeat,
    ];
  }

}
