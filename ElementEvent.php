<?php

namespace SPTK;

trait ElementEvent {

  public function eventHandler(array $event): bool {
    if (!$this->display) {
      return false;
    }
    $n = count($this->stack);
    if ($n > 0) {
      for ($i = 0; $i < $n; $i++) {
        $descendant = $this->stack[($n + $i - 1) % $n];
        if ($descendant->display) {
          if ($descendant->eventHandler($event)) {
            return true;
          }
          break;
        }
      }
    }
    if (isset($event['name']) && isset($this->events[$event['name']])) {
      return call_user_func($this->events[$event['name']], $this, $event);
    }
    return false;
  }

  public function addEvent(string $event, array|string $handler): void {
    if (!is_array($handler)) {
      $handler = preg_split('/::/', $handler);
    }
    $this->events[$event] = $handler;
  }

  public function removeEvent(string $event): void {
    unset($this->events[$event]);
  }

}
