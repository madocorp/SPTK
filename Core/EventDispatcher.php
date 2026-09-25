<?php

namespace SPTK\Core;

/** Invokes matching XML event actions and reports whether raw input was consumed. */
final class EventDispatcher {

  /** Dispatch matching actions, stopping only when consumable input is handled. */
  public function dispatch(array $events, EventContext $context, bool $consumable): bool {
    foreach ($events as $event) {
      if (!$event instanceof EventDefinition || $event->type !== $context->type || !$event->matches($context->input)) {
        continue;
      }
      if (!is_callable($event->action)) {
        throw new \RuntimeException("Event action is not callable: {$event->action}");
      }
      $handled = call_user_func($event->action, $context);
      if ($consumable && $handled === true) {
        return true;
      }
    }
    return false;
  }

}
