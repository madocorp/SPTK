<?php

namespace SPTK;

class KeyCode {

  const UNKNOWN = 0;

  const RETURN = 13;
  const ESCAPE = 27;
  const BACKSPACE = 8;
  const TAB = 9;
  const SPACE = 32;

  const EXCLAIM = 33;
  const QUOTEDBL = 34;
  const HASH = 35;
  const DOLLAR = 36;
  const PERCENT = 37;
  const AMPERSAND = 38;
  const QUOTE = 39;
  const LEFTPAREN = 40;
  const RIGHTPAREN = 41;
  const ASTERISK = 42;
  const PLUS = 43;
  const COMMA = 44;
  const MINUS = 45;
  const PERIOD = 46;
  const SLASH = 47;

  const NUM_0 = 48;
  const NUM_1 = 49;
  const NUM_2 = 50;
  const NUM_3 = 51;
  const NUM_4 = 52;
  const NUM_5 = 53;
  const NUM_6 = 54;
  const NUM_7 = 55;
  const NUM_8 = 56;
  const NUM_9 = 57;

  const COLON = 58;
  const SEMICOLON = 59;
  const LESS = 60;
  const EQUALS = 61;
  const GREATER = 62;
  const QUESTION = 63;
  const AT = 64;

  // Letters (lowercase by SDL design)
  const A = 97;
  const B = 98;
  const C = 99;
  const D = 100;
  const E = 101;
  const F = 102;
  const G = 103;
  const H = 104;
  const I = 105;
  const J = 106;
  const K = 107;
  const L = 108;
  const M = 109;
  const N = 110;
  const O = 111;
  const P = 112;
  const Q = 113;
  const R = 114;
  const S = 115;
  const T = 116;
  const U = 117;
  const V = 118;
  const W = 119;
  const X = 120;
  const Y = 121;
  const Z = 122;

  const LEFTBRACKET = 91;
  const BACKSLASH = 92;
  const RIGHTBRACKET = 93;
  const CARET = 94;
  const UNDERSCORE = 95;
  const BACKQUOTE = 96;

  // Non-printable / special keys
  const DELETE = 127;

  // Function keys (SDL_SCANCODE_MASK = 1 << 30)
  const SCANCODE_MASK = 1 << 30;

  const F1  = self::SCANCODE_MASK | 58;
  const F2  = self::SCANCODE_MASK | 59;
  const F3  = self::SCANCODE_MASK | 60;
  const F4  = self::SCANCODE_MASK | 61;
  const F5  = self::SCANCODE_MASK | 62;
  const F6  = self::SCANCODE_MASK | 63;
  const F7  = self::SCANCODE_MASK | 64;
  const F8  = self::SCANCODE_MASK | 65;
  const F9  = self::SCANCODE_MASK | 66;
  const F10 = self::SCANCODE_MASK | 67;
  const F11 = self::SCANCODE_MASK | 68;
  const F12 = self::SCANCODE_MASK | 69;

  const INSERT   = self::SCANCODE_MASK | 73;
  const HOME   = self::SCANCODE_MASK | 74;
  const PAGEUP   = self::SCANCODE_MASK | 75;
  const END    = self::SCANCODE_MASK | 77;
  const PAGEDOWN = self::SCANCODE_MASK | 78;

  const RIGHT = self::SCANCODE_MASK | 79;
  const LEFT  = self::SCANCODE_MASK | 80;
  const DOWN  = self::SCANCODE_MASK | 81;
  const UP  = self::SCANCODE_MASK | 82;

}
