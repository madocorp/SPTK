<?php

namespace SPTK\Events;

/** Adds named event subscriptions to a widget. */
trait WidgetEventEmitter {

  private array $widgetListeners = [];

  /** Subscribe a callable to a widget event. */
  public function on(string $event, callable $listener): void {
    $this->widgetListeners[$event][] = $listener;
  }

  /** Invoke listeners registered for a widget event. */
  public function emit(string $event): void {
    foreach ($this->widgetListeners[$event] ?? [] as $listener) {
      $listener();
    }
  }

}
