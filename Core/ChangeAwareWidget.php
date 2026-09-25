<?php

namespace SPTK\Core;

/** Optional widget contract for reporting committed value changes to its screen. */
interface ChangeAwareWidget {

  /** Set the listener called without arguments after the widget value changes. */
  public function setChangeListener(?callable $listener): void;

}
