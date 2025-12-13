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
    static::$root->render();
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

  public static function getRelativePos($referenceId, $element, &$x, &$y) {
    if ($element->iid == $referenceId) {
      return;
    }
    $x += $element->geometry->x;
    $y += $element->geometry->y;
    static::getRelativePos($referenceId, $element->ancestor, $x, $y);
  }

  public static function immediateRender($element) {
    $t = microtime(true);
    $element->ancestor->cursor->reset();
    $element->calculateGeometry();
    $tmpTexture = $element->render();
    $window = $element->findAncestorByType('Window');
    $x = 0;
    $y = 0;
    static::getRelativePos($window->iid, $element, $x, $y);
    $tmpTexture->copyTo($window->tmpTexture, $x, $y);
    $window->tmpTexture->copyTo(null, 0, 0);
    $window->sdl->SDL_RenderPresent($window->renderer);
    if (DEBUG) {
      echo "Immediate refresh:", microtime(true) - $t, "\n";
    }
  }

  public static function parseCallback($value) {
    if (empty($value)) {
      return false;
    }
    $function = explode('::', $value);
    if (!is_array($function) || count($function) !== 2) {
      throw new \Exception("Malformed callback function: '{$value}'");
    }
    return $function;
  }

}
