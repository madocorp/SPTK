<?php

namespace SPTK\Events;

use SPTK\Core\Widget;

/** Carries an event and its optional widget and native input source to an action. */
final class EventContext {

  /** Create the context passed to a static event action. */
  public function __construct(
    public readonly string $type,
    public readonly ?Widget $widget = null,
    public readonly mixed $input = null,
  ) {
  }

}
