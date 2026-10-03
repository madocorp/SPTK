<?php

namespace SPTK\SDLWrapper;

/** Simulates a missing libpng runtime in an isolated fallback test process. */
final class PNG {

  public static int $attempts = 0;

  /** Count loading attempts and report the same exception type as a failed FFI load. */
  public function __construct() {
    self::$attempts++;
    throw new \FFI\Exception('Failed loading libpng16.so.16');
  }

}
