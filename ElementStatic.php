<?php

namespace SPTK;

trait ElementStatic {

  public static $root;
  private static $nextInternalId = 0;

  protected static function getNextId(): int {
    $id = static::$nextInternalId;
    static::$nextInternalId++;
    return $id;
  }

  public static function refresh(): void {
    $t = microtime(true);
    static::$root->recalculateGeometry();
    static::$root->render();
    // DEBUG:5 echo "Refreshed:", microtime(true) - $t, "\n";
  }

  public static function immediateRender(Element $element, bool $layout = true): void {
    $t = microtime(true);
    if ($layout) {
      $element->recalculateGeometry();
    } else {
      $element->redraw();
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
    // DEBUG:5  echo "Immediate refresh:", microtime(true) - $t, ($layout ? ' with recalculate' : ''), "\n";
  }

  public static function byName(string $name, ?Element $element = null): Element|false {
    if ($element === null) {
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

  public static function firstByType(string $type, ?Element $element = null): Element|false {
    if ($element === null) {
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

  public static function allByType(string $type, ?Element $element = null): array {
    $elements = [];
    if ($element === null) {
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

  public static function getRelativePos(int $referenceId, Element $element, int &$x, int &$y) {
    if ($element->id == $referenceId) {
      return;
    }
    $x += $element->geometry->x;
    $y += $element->geometry->y;
    static::getRelativePos($referenceId, $element->ancestor, $x, $y);
  }


  public static function parseCallback(string|array $value): array|false {
    if (empty($value)) {
      return false;
    }
    if (is_array($value)) {
      return $value;
    }
    $function = explode('::', $value);
    if (count($function) !== 2) {
      throw new \Exception("Malformed callback function: '{$value}'");
    }
    return $function;
  }

}
