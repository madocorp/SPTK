<?php

namespace SPTK\Events;

use SPTK\SDLWrapper\SDL;

/** Normalizes SDL keycodes so equivalent keyboard keys share one code. */
final class KeyNormalizer {

  /** Return the SDL modifier bits supported by key chords. */
  public static function normalizeModifiers(int $mod): int {
    $normalized = 0;
    if (($mod & SDL::MOD_CTRL) !== 0) {
      $normalized |= SDL::MOD_CTRL;
    }
    if (($mod & SDL::MOD_SHIFT) !== 0) {
      $normalized |= SDL::MOD_SHIFT;
    }
    if (($mod & SDL::MOD_ALT) !== 0) {
      $normalized |= SDL::MOD_ALT;
    }
    return $normalized;
  }

  /** Return the canonical spelling for a modifier name. */
  public static function normalizeModifierName(string $modifier): ?string {
    $modifier = strtolower($modifier);
    return in_array($modifier, ['ctrl', 'shift', 'alt'], true) ? $modifier : null;
  }

  /** Return the SDL modifier bits for a canonical modifier name. */
  public static function modifierMask(string $modifier): int {
    return match (self::normalizeModifierName($modifier)) {
      'ctrl' => SDL::MOD_CTRL,
      'shift' => SDL::MOD_SHIFT,
      'alt' => SDL::MOD_ALT,
      default => 0,
    };
  }

  /** Convert keypad navigation keys to their standard keycodes. */
  public static function normalize(int $key, int $mod): int {
    if ($key === SDL::KEY_KP_ENTER) {
      return SDL::KEY_RETURN;
    }
    if (($mod & SDL::MOD_NUM) !== 0) {
      return $key;
    }
    return match ($key) {
      SDL::KEY_KP_7 => SDL::KEY_HOME,
      SDL::KEY_KP_1 => SDL::KEY_END,
      SDL::KEY_KP_9 => SDL::KEY_PAGEUP,
      SDL::KEY_KP_3 => SDL::KEY_PAGEDOWN,
      SDL::KEY_KP_4 => SDL::KEY_LEFT,
      SDL::KEY_KP_6 => SDL::KEY_RIGHT,
      SDL::KEY_KP_8 => SDL::KEY_UP,
      SDL::KEY_KP_2 => SDL::KEY_DOWN,
      SDL::KEY_KP_0 => SDL::KEY_INSERT,
      SDL::KEY_KP_PERIOD => SDL::KEY_DELETE,
      default => $key,
    };
  }

  /** Return the canonical XML key name for an SDL keycode and modifier mask. */
  public static function keyName(int $key, int $mod): string {
    $key = self::normalize($key, $mod);
    $names = [
      SDL::KEY_RETURN => 'enter',
      SDL::KEY_ESCAPE => 'escape',
      SDL::KEY_BACKSPACE => 'backspace',
      SDL::KEY_TAB => 'tab',
      SDL::KEY_SPACE => 'space',
      SDL::KEY_DELETE => 'delete',
      SDL::KEY_UP => 'up',
      SDL::KEY_DOWN => 'down',
      SDL::KEY_LEFT => 'left',
      SDL::KEY_RIGHT => 'right',
      SDL::KEY_HOME => 'home',
      SDL::KEY_END => 'end',
      SDL::KEY_PAGEUP => 'pageup',
      SDL::KEY_PAGEDOWN => 'pagedown',
      SDL::KEY_INSERT => 'insert',
      SDL::KEY_F1 => 'f1',
      SDL::KEY_F2 => 'f2',
      SDL::KEY_F3 => 'f3',
      SDL::KEY_F4 => 'f4',
      SDL::KEY_F5 => 'f5',
      SDL::KEY_F6 => 'f6',
      SDL::KEY_F7 => 'f7',
      SDL::KEY_F8 => 'f8',
      SDL::KEY_F9 => 'f9',
      SDL::KEY_F10 => 'f10',
      SDL::KEY_F11 => 'f11',
      SDL::KEY_F12 => 'f12',
    ];
    if (isset($names[$key])) {
      return $names[$key];
    }
    if ($key >= 32 && $key <= 126) {
      return strtolower(chr($key));
    }
    return '';
  }

  /** Canonicalize and validate a key name used in an XML key chord. */
  public static function normalizeName(string $name): ?string {
    $name = strtolower($name);
    $name = match ($name) {
      'return' => 'enter',
      'esc' => 'escape',
      default => $name,
    };
    $namedKeys = ['enter', 'escape', 'backspace', 'tab', 'space', 'delete', 'up', 'down', 'left', 'right', 'home', 'end', 'pageup', 'pagedown', 'insert'];
    if (in_array($name, $namedKeys, true) || preg_match('/^(?:[a-z0-9]|f(?:[1-9]|1[0-2]))$/', $name)) {
      return $name;
    }
    return null;
  }

}
