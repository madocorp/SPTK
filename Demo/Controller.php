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

  /** Show the choice widgets in the event's window. */
  public static function showChoices(\SPTK\Events\EventContext $event): bool {
    \SPTK\App::eventLoop()->window((int)$event->input->key->windowID)?->setCurrentScreen(2);
    return true;
  }

  /** Show the list widgets in the event's window. */
  public static function showLists(\SPTK\Events\EventContext $event): bool {
    \SPTK\App::eventLoop()->window((int)$event->input->key->windowID)?->setCurrentScreen(3);
    return true;
  }

  /** Show the image examples in the event's window. */
  public static function showImages(\SPTK\Events\EventContext $event): bool {
    \SPTK\App::eventLoop()->window((int)$event->input->key->windowID)?->setCurrentScreen(4);
    return true;
  }

  /** Print changed choice values from the demo. */
  public static function choiceChanged(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
  }

  /** Print changed list values from the demo. */
  public static function listChanged(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
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
