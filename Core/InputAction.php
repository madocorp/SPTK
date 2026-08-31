<?php

namespace SPTK\Core;

/**
 * Centralizes semantic keyboard actions and common key aliases.
 */
final class InputAction {

  public static function left(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Left');
  }

  public static function right(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Right');
  }

  public static function up(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Up');
  }

  public static function down(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Down');
  }

  public static function home(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Home');
  }

  public static function end(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'End');
  }

  public static function pageUp(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'PageUp');
  }

  public static function pageDown(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'PageDown');
  }

  public static function selectAll(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'A', ['ctrl' => true]);
  }

  public static function copy(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'C', ['ctrl' => true]) || self::combo($event, 'Insert', ['ctrl' => true]);
  }

  public static function cut(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'X', ['ctrl' => true]) || self::combo($event, 'Delete', ['ctrl' => true]);
  }

  public static function paste(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'V', ['ctrl' => true]) || self::combo($event, 'Insert', ['shift' => true]);
  }

  public static function undo(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'Z', ['ctrl' => true]);
  }

  public static function redo(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'Y', ['ctrl' => true]);
  }

  public static function backspace(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Backspace');
  }

  public static function delete(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Delete');
  }

  public static function activate(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Enter') || self::key($event, 'Space');
  }

  public static function select(InputEvent $event, string $context = ''): bool {
    return self::activate($event, $context);
  }

  public static function cancel(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Escape');
  }

  public static function confirm(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'Enter', ['ctrl' => true]);
  }

  public static function newline(InputEvent $event, string $context = ''): bool {
    return self::key($event, 'Enter');
  }

  public static function selectRow(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'Space', ['shift' => true]);
  }

  public static function selectColumn(InputEvent $event, string $context = ''): bool {
    return self::combo($event, 'Space', ['ctrl' => true]);
  }

  public static function normalizedKey(string $key): string {
    static $aliases = [
      'Return' => 'Enter',
      'KP_Enter' => 'Enter',
      'KeypadEnter' => 'Enter',
      'NumpadEnter' => 'Enter',
      'Esc' => 'Escape',
      'Del' => 'Delete',
      'PgUp' => 'PageUp',
      'Page_Up' => 'PageUp',
      'KP_PageUp' => 'PageUp',
      'KeypadPageUp' => 'PageUp',
      'NumpadPageUp' => 'PageUp',
      'PgDn' => 'PageDown',
      'Page_Down' => 'PageDown',
      'KP_PageDown' => 'PageDown',
      'KeypadPageDown' => 'PageDown',
      'NumpadPageDown' => 'PageDown',
      'KP_Left' => 'Left',
      'KeypadLeft' => 'Left',
      'NumpadLeft' => 'Left',
      'KP_Right' => 'Right',
      'KeypadRight' => 'Right',
      'NumpadRight' => 'Right',
      'KP_Up' => 'Up',
      'KeypadUp' => 'Up',
      'NumpadUp' => 'Up',
      'KP_Down' => 'Down',
      'KeypadDown' => 'Down',
      'NumpadDown' => 'Down',
      'KP_Home' => 'Home',
      'KeypadHome' => 'Home',
      'NumpadHome' => 'Home',
      'KP_End' => 'End',
      'KeypadEnd' => 'End',
      'NumpadEnd' => 'End',
      'KP_Insert' => 'Insert',
      'KeypadInsert' => 'Insert',
      'NumpadInsert' => 'Insert',
      'KP_Delete' => 'Delete',
      'KeypadDelete' => 'Delete',
      'NumpadDelete' => 'Delete',
    ];
    if (isset($aliases[$key])) {
      return $aliases[$key];
    }
    if (mb_strlen($key) === 1) {
      return strtoupper($key);
    }
    return $key;
  }

  protected static function key(InputEvent $event, string $key): bool {
    return $event->type === 'key' && self::normalizedKey($event->key) === $key;
  }

  protected static function combo(InputEvent $event, string $key, array $modifiers): bool {
    if (!self::key($event, $key)) {
      return false;
    }
    foreach ($modifiers as $modifier => $required) {
      if (($event->modifiers[$modifier] ?? false) !== $required) {
        return false;
      }
    }
    return true;
  }

}
