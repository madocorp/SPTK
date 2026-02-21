<?php

namespace SPTK\SDLWrapper;

class KeyModifier {

  const NONE = 0x0000;

  const LSHIFT = 0x0001; /* the left Shift key is down. */
  const RSHIFT = 0x0002; /* the right Shift key is down. */
  const LEVEL5 = 0x0004; /* the Level 5 Shift key is down. */
  const LCTRL = 0x0040;  /* the left Ctrl (Control) key is down. */
  const RCTRL = 0x0080;  /* the right Ctrl (Control) key is down. */
  const LALT = 0x0100;   /* the left Alt key is down. */
  const RALT = 0x0200;   /* the right Alt key is down. */
  const LGUI = 0x0400;   /* the left GUI key (often the Windows key) is down. */
  const RGUI = 0x0800;   /* the right GUI key (often the Windows key) is down. */
  const NUM = 0x1000;    /* the Num Lock key (may be located on an extended keypad) is down. */
  const CAPS = 0x2000;   /* the Caps Lock key is down. */
  const MODE = 0x4000;   /* the !AltGr key is down. */
  const SCROLL = 0x8000; /* the Scroll Lock key is down. */

  const SHIFT = 0x0003;
  const CTRL = 0x00c0;
  const ALT = 0x0300;
  const GUI = 0x0c00;

  const PRIMARY = (PHP_OS_FAMILY == 'Darwin' ? self::GUI : self::CTRL);

}
