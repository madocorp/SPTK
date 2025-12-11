<?php

namespace SPTK;

trait ElementStatic {

  public static $root;
  protected static $elementsById = [];
  private static $nextInternalId = 0;

  protected static function getNextId() {
    $iid = static::$nextInternalId;
    static::$nextInternalId++;
    return $iid;
  }

  public static function refresh() {
    $t = microtime(true);
    static::$root->calculateGeometry();
    static::$root->render(null);
    if (DEBUG) {
      echo "Refreshed:", microtime(true) - $t, "\n";
    }
  }

  public static function event($event) {
    static::$root->eventHandler($event);
  }

  public static function getById($id) {
    if (!isset(static::$elementsById[$id])) {
      throw new \Exception("Element not found by id: {$id}");
    }
    return static::$elementsById[$id];
  }

}
