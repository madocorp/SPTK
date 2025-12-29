<?php

namespace SPTK;

class KeyCombo {

  private static $map = [];

  public static function init() {

    // Clipboard
    self::bind(KeyModifier::PRIMARY, ScanCode::C, Action::COPY);
    self::bind(KeyModifier::PRIMARY, ScanCode::INSERT, Action::COPY);
    self::bind(KeyModifier::PRIMARY, ScanCode::X, Action::CUT);
    self::bind(KeyModifier::PRIMARY, ScanCode::DELETE, Action::CUT);
    self::bind(KeyModifier::PRIMARY, ScanCode::V, Action::PASTE);
    self::bind(KeyModifier::SHIFT, ScanCode::INSERT, Action::PASTE);

    // Move
    self::bind(KeyModifier::NONE, ScanCode::LEFT, Action::MOVE_LEFT);
    self::bind(KeyModifier::NONE, ScanCode::RIGHT, Action::MOVE_RIGHT);
    self::bind(KeyModifier::NONE, ScanCode::UP, Action::MOVE_UP);
    self::bind(KeyModifier::NONE, ScanCode::DOWN, Action::MOVE_DOWN);
    self::bind(KeyModifier::NONE, ScanCode::HOME, Action::MOVE_START);
    self::bind(KeyModifier::NONE, ScanCode::KP_7, Action::MOVE_START);
    self::bind(KeyModifier::NONE, ScanCode::END, Action::MOVE_END);
    self::bind(KeyModifier::NONE, ScanCode::KP_1, Action::MOVE_END);
    self::bind(KeyModifier::NONE, ScanCode::PAGEUP, Action::PAGE_UP);
    self::bind(KeyModifier::NONE, ScanCode::PAGEDOWN, Action::PAGE_DOWN);

    // Select
    self::bind(KeyModifier::SHIFT, ScanCode::LEFT, Action::SELECT_LEFT);
    self::bind(KeyModifier::SHIFT, ScanCode::RIGHT, Action::SELECT_RIGHT);
    self::bind(KeyModifier::SHIFT, ScanCode::UP, Action::SELECT_UP);
    self::bind(KeyModifier::SHIFT, ScanCode::DOWN, Action::SELECT_DOWN);
    self::bind(KeyModifier::SHIFT, ScanCode::HOME, Action::SELECT_START);
    self::bind(KeyModifier::SHIFT, ScanCode::KP_7, Action::SELECT_START);
    self::bind(KeyModifier::SHIFT, ScanCode::END, Action::SELECT_END);
    self::bind(KeyModifier::SHIFT, ScanCode::KP_1, Action::SELECT_END);

    // Switch fields
    self::bind(KeyModifier::PRIMARY, ScanCode::LEFT, Action::SWITCH_LEFT);
    self::bind(KeyModifier::NONE, ScanCode::KP_4, Action::SWITCH_LEFT);
    self::bind(KeyModifier::PRIMARY, ScanCode::RIGHT, Action::SWITCH_RIGHT);
    self::bind(KeyModifier::NONE, ScanCode::KP_6, Action::SWITCH_RIGHT);
    self::bind(KeyModifier::PRIMARY, ScanCode::UP, Action::SWITCH_UP);
    self::bind(KeyModifier::NONE, ScanCode::KP_8, Action::SWITCH_UP);
    self::bind(KeyModifier::PRIMARY, ScanCode::DOWN, Action::SWITCH_DOWN);
    self::bind(KeyModifier::NONE, ScanCode::KP_2, Action::SWITCH_DOWN);
    self::bind(KeyModifier::NONE, ScanCode::TAB, Action::SWITCH_NEXT);
    self::bind(KeyModifier::SHIFT, ScanCode::TAB, Action::SWITCH_PREVIOUS);

    // Delete
    self::bind(KeyModifier::NONE, ScanCode::DELETE, Action::DELETE_FORWARD);
    self::bind(KeyModifier::NONE, ScanCode::BACKSPACE, Action::DELETE_BACK);

    self::bind(KeyModifier::NONE, ScanCode::ESCAPE, Action::CLOSE);
    self::bind(KeyModifier::NONE, ScanCode::RETURN, Action::DO_IT);
    self::bind(KeyModifier::NONE, ScanCode::SPACE, Action::SELECT_ITEM);

  }

  private static function bind($modifier, $scancode, $action) {
    $modifier = self::normalizeModifier($modifier);
    $hash = "{$modifier}:{$scancode}";
    self::$map[$hash] = $action;
  }

  public static function resolve($modifier, $scancode, $keycode = false) {
    $modifier = self::normalizeModifier($modifier);
    $hash = "{$modifier}:{$scancode}";
    return self::$map[$hash] ?? $keycode;
  }

  private static function normalizeModifier($modifier) {
    $result = 0;
    if ($modifier & KeyModifier::CTRL) {
      $result |= 1;
    }
    if ($modifier & KeyModifier::SHIFT) {
      $result |= 2;
    }
    if ($modifier & KeyModifier::ALT) {
      $result |= 4;
    }
    if ($modifier & KeyModifier::GUI) {
      $result |= 8;
    }
    return $result;
  }

}
