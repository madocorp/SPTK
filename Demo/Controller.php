<?php

/** Handles demo lifecycle messages and screen navigation. */
class Controller {

  /** Print the selected RGB value from the color selector. */
  public static function colorChanged(\SPTK\Events\EventContext $event): void {
    echo $event->widget->getValue() . "\n";
  }

  /** Print the selected ISO date from the calendar. */
  public static function dateChanged(\SPTK\Events\EventContext $event): void {
    echo $event->widget->getValue() . "\n";
  }

  /** Print the accepted path from the file selector. */
  public static function fileAccepted(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
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
