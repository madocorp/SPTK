<?php

namespace SPTK;

trait ElementStatic {

  public static $root;
  private static $nextInternalId = 0;

  protected static function getNextId() {
    $id = static::$nextInternalId;
    static::$nextInternalId++;
    return $id;
  }

  public static function refresh() {
    $t = microtime(true);
    static::$root->recalculateGeometry();
    static::$root->render();
    if (DEBUG) {
      echo "Refreshed:", microtime(true) - $t, "\n";
    }
  }

  public static function immediateRender($element, $layout = true) {
    $t = microtime(true);
    if ($layout) {
      $element->recalculateGeometry();
    }
    $tmpTexture = $element->render();
    if ($tmpTexture === false) {
      Element::refresh();
      return;
    }
    $window = $element->findAncestorByType('Window');
    if ($window->tmpTexture === false) {
      Element::refresh();
      return;
    }
    $x = 0;
    $y = 0;
    static::getRelativePos($window->id, $element, $x, $y);
    $tmpTexture->copyTo($window->tmpTexture, $x, $y);
    $window->tmpTexture->copyTo(null, 0, 0);
    $window->sdl->SDL_RenderPresent($window->renderer);
    if (DEBUG) {
      echo "Immediate refresh:", microtime(true) - $t, "\n";
    }
  }

  public static function event($event) {
    static::$root->eventHandler($event);
  }

  public static function byName($name, $element = false) {
    if ($element === false) {
      $element = static::$root;
    }
    $q = [$element];
    while (!empty($q)) {
      $e = array_shift($q);
      if ($e->name === $name) {
        return $e;
      }
      foreach ($e->descendants as $descendant) {
        $q[] = $descendant;
      }
    }
    return false;
  }

  public static function firstByType($type, $element = false) {
    if ($element === false) {
      $element = static::$root;
    }
    $q = [$element];
    while (!empty($q)) {
      $e = array_shift($q);
      if ($e->type === $type) {
        return $e;
      }
      foreach ($e->descendants as $descendant) {
        $q[] = $descendant;
      }
    }
    return false;
  }

  public static function allByType($type, $element = false) {
    $elements = [];
    if ($element === false) {
      $element = static::$root;
    }
    $q = [$element];
    while (!empty($q)) {
      $e = array_shift($q);
      if ($e->type === $type) {
        $elements[] = $e;
      }
      foreach ($e->descendants as $descendant) {
        $q[] = $descendant;
      }
    }
    return $elements;
  }

  public static function getRelativePos($referenceId, $element, &$x, &$y) {
    if ($element->id == $referenceId) {
      return;
    }
    $x += $element->geometry->x;
    $y += $element->geometry->y;
    static::getRelativePos($referenceId, $element->ancestor, $x, $y);
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
