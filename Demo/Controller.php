<?php

/** Handles demo lifecycle messages and screen navigation. */
class Controller {

  /** Show the original demo screen in the event's window. */
  public static function showMain(\SPTK\Events\EventContext $event): bool {
    \SPTK\App::eventLoop()->window((int)$event->input->key->windowID)?->setCurrentScreen(0);
    return true;
  }

  /** Show the editors screen in the event's window. */
  public static function showEditors(\SPTK\Events\EventContext $event): bool {
    \SPTK\App::eventLoop()->window((int)$event->input->key->windowID)?->setCurrentScreen(1);
    return true;
  }

  /** Print a demo callback message. */
  public static function test(): bool {
    echo "test callback\n";
    return true;
  }

  /** Print a callback message from the top text tile. */
  public static function testTop(): bool {
    echo "testTop callback\n";
    return true;
  }

  /** Print a message when the demo starts. */
  public static function init(): bool {
    echo "init callback\n";
    return true;
  }

}
