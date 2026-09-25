<?php

namespace SPTK\Core;

/** Defines one XML event subscription and its static controller action. */
final class EventDefinition {

  /** Create a parsed event subscription. */
  public function __construct(
    public readonly string $type,
    public readonly ?string $key,
    public readonly string $action,
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
    $actualModifiers = 0;
    if (($nativeModifiers & \SPTK\SDLWrapper\SDL::MOD_CTRL) !== 0) {
      $actualModifiers |= \SPTK\SDLWrapper\SDL::MOD_CTRL;
    }
    if (($nativeModifiers & \SPTK\SDLWrapper\SDL::MOD_SHIFT) !== 0) {
      $actualModifiers |= \SPTK\SDLWrapper\SDL::MOD_SHIFT;
    }
    if (($nativeModifiers & \SPTK\SDLWrapper\SDL::MOD_ALT) !== 0) {
      $actualModifiers |= \SPTK\SDLWrapper\SDL::MOD_ALT;
    }
    if ($actualModifiers !== $modifiers) {
      return false;
    }
    return self::keyName((int)$input->key->key) === $expectedKey;
  }

  /** Convert a normalized key chord into its modifier mask and key name. */
  private static function keyParts(string $key): array {
    $parts = explode('+', $key);
    $name = array_pop($parts);
    $modifiers = 0;
    foreach ($parts as $part) {
      $modifiers |= match ($part) {
        'ctrl' => \SPTK\SDLWrapper\SDL::MOD_CTRL,
        'shift' => \SPTK\SDLWrapper\SDL::MOD_SHIFT,
        'alt' => \SPTK\SDLWrapper\SDL::MOD_ALT,
        default => 0,
      };
    }
    return [$modifiers, $name];
  }

  /** Convert an SDL keycode into the key spelling accepted by EventParser. */
  private static function keyName(int $key): string {
    $names = [
      \SPTK\SDLWrapper\SDL::KEY_RETURN => 'enter',
      \SPTK\SDLWrapper\SDL::KEY_KP_ENTER => 'enter',
      \SPTK\SDLWrapper\SDL::KEY_ESCAPE => 'escape',
      \SPTK\SDLWrapper\SDL::KEY_BACKSPACE => 'backspace',
      \SPTK\SDLWrapper\SDL::KEY_TAB => 'tab',
      \SPTK\SDLWrapper\SDL::KEY_SPACE => 'space',
      \SPTK\SDLWrapper\SDL::KEY_DELETE => 'delete',
      \SPTK\SDLWrapper\SDL::KEY_UP => 'up',
      \SPTK\SDLWrapper\SDL::KEY_DOWN => 'down',
      \SPTK\SDLWrapper\SDL::KEY_LEFT => 'left',
      \SPTK\SDLWrapper\SDL::KEY_RIGHT => 'right',
      \SPTK\SDLWrapper\SDL::KEY_HOME => 'home',
      \SPTK\SDLWrapper\SDL::KEY_END => 'end',
      \SPTK\SDLWrapper\SDL::KEY_PAGEUP => 'pageup',
      \SPTK\SDLWrapper\SDL::KEY_PAGEDOWN => 'pagedown',
      \SPTK\SDLWrapper\SDL::KEY_INSERT => 'insert',
    ];
    if (isset($names[$key])) {
      return $names[$key];
    }
    if ($key >= 32 && $key <= 126) {
      return strtolower(chr($key));
    }
    return match ($key) {
      \SPTK\SDLWrapper\SDL::KEY_F1 => 'f1',
      \SPTK\SDLWrapper\SDL::KEY_F2 => 'f2',
      \SPTK\SDLWrapper\SDL::KEY_F3 => 'f3',
      \SPTK\SDLWrapper\SDL::KEY_F4 => 'f4',
      \SPTK\SDLWrapper\SDL::KEY_F5 => 'f5',
      \SPTK\SDLWrapper\SDL::KEY_F6 => 'f6',
      \SPTK\SDLWrapper\SDL::KEY_F7 => 'f7',
      \SPTK\SDLWrapper\SDL::KEY_F8 => 'f8',
      \SPTK\SDLWrapper\SDL::KEY_F9 => 'f9',
      \SPTK\SDLWrapper\SDL::KEY_F10 => 'f10',
      \SPTK\SDLWrapper\SDL::KEY_F11 => 'f11',
      \SPTK\SDLWrapper\SDL::KEY_F12 => 'f12',
      default => '',
    };
  }

}
