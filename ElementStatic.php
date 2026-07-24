<?php

namespace SPTK;

trait ElementStatic {

  public static $root;
  private static $nextInternalId = 0;
  private static int $refreshBatchDepth = 0;
  private static bool $refreshFullRequested = false;
  private static array $refreshElements = [];
  private static bool $refreshFlushing = false;

  protected static function getNextId(): int {
    $id = static::$nextInternalId;
    static::$nextInternalId++;
    return $id;
  }

  public static function refresh(): void {
    static::requestRefresh(null, 'full');
  }

  public static function refreshNow(): void {
    $t = microtime(true);
    static::$root->recalculateGeometry();
    static::$root->render();
    // DEBUG:refresh echo "Refreshed:", microtime(true) - $t, "\n";
  }

  public static function requestRefresh(?Element $element = null, string $mode = 'auto'): void {
    if (static::$root === null || static::$refreshFlushing) {
      return;
    }
    if ($element === null || $mode === 'full') {
      static::$refreshFullRequested = true;
      static::$refreshElements = [];
    } else if (!static::$refreshFullRequested) {
      static::$refreshElements[$element->id] = [
        'element' => $element,
        'layout' => $mode !== 'redraw'
      ];
    }
    if (static::$refreshBatchDepth === 0) {
      static::flushRefresh();
    }
  }

  public static function beginBatch(): void {
    static::$refreshBatchDepth++;
  }

  public static function endBatch(): void {
    if (static::$refreshBatchDepth > 0) {
      static::$refreshBatchDepth--;
    }
    if (static::$refreshBatchDepth === 0) {
      static::flushRefresh();
    }
  }

  public static function flushRefresh(): void {
    if (static::$root === null || static::$refreshFlushing) {
      return;
    }
    if (!static::$refreshFullRequested && empty(static::$refreshElements)) {
      return;
    }
    $full = static::$refreshFullRequested || count(static::$refreshElements) !== 1;
    $elements = static::$refreshElements;
    static::$refreshFullRequested = false;
    static::$refreshElements = [];
    static::$refreshFlushing = true;
    try {
      if ($full) {
        static::refreshNow();
      } else {
        $refresh = reset($elements);
        if ($refresh === false || !static::isRefreshElementVisible($refresh['element'])) {
          static::refreshNow();
        } else {
          static::immediateRenderNow($refresh['element'], $refresh['layout']);
        }
      }
    } finally {
      static::$refreshFlushing = false;
    }
  }

  public static function immediateRender(Element $element, bool $layout = true): void {
    if (static::$refreshBatchDepth > 0) {
      static::requestElementRefresh($element, $layout);
      return;
    }
    static::immediateRenderNow($element, $layout);
  }

  private static function requestElementRefresh(Element $element, bool $layout = true): void {
    if (static::$refreshFullRequested) {
      return;
    }
    $id = $element->id;
    if (isset(static::$refreshElements[$id])) {
      static::$refreshElements[$id]['layout'] = static::$refreshElements[$id]['layout'] || $layout;
    } else {
      static::$refreshElements[$id] = [
        'element' => $element,
        'layout' => $layout
      ];
    }
  }

  private static function immediateRenderNow(Element $element, bool $layout = true): void {
    $t = microtime(true);
    if ($layout) {
      $element->recalculateGeometry();
    } else {
      $element->redraw();
    }
    $tmpTexture = $element->render();
    if ($tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $window = $element->findAncestorByType('Window');
    if ($window->tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $x = 0;
    $y = 0;
    static::getRelativePos($window->id, $element, $x, $y);
    $tmpTexture->copyTo($window->tmpTexture, $x, $y);
    $window->tmpTexture->copyTo(null, 0, 0);
    $window->sdl->SDL_RenderPresent($window->renderer);
    // DEBUG:refresh  echo "Immediate refresh:", microtime(true) - $t, ($layout ? ' with recalculate' : ''), "\n";
  }

  public static function immediateRefresh(Element $element, bool $layout = true): void {
    if (static::$refreshBatchDepth > 0) {
      static::requestElementRefresh($element, $layout);
      return;
    }
    $t = microtime(true);
    if ($layout) {
      $element->recalculateGeometry();
    } else {
      $element->redraw();
    }
    $tmpTexture = $element->render();
    if ($tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $window = $element->findAncestorByType('Window');
    if ($window->tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $x = 0;
    $y = 0;
    static::getRenderedRelativePos($window->id, $element, $x, $y);
    $tmpTexture->copyTo($window->tmpTexture, $x, $y);
    $window->tmpTexture->copyTo(null, 0, 0);
    $window->sdl->SDL_RenderPresent($window->renderer);
    // DEBUG:refresh  echo "Immediate small refresh:", microtime(true) - $t, ($layout ? ' with recalculate' : ''), "\n";
  }

  public static function immediateCopy(Element $element): void {
    if (static::$refreshBatchDepth > 0) {
      static::requestElementRefresh($element, false);
      return;
    }
    $tmpTexture = $element->render();
    if ($tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $window = $element->findAncestorByType('Window');
    if ($window->tmpTexture === false) {
      Element::refreshNow();
      return;
    }
    $x = 0;
    $y = 0;
    static::getRenderedRelativePos($window->id, $element, $x, $y);
    $tmpTexture->copyTo($window->tmpTexture, $x, $y);
    $window->tmpTexture->copyTo(null, 0, 0);
    $window->sdl->SDL_RenderPresent($window->renderer);
  }

  private static function isRefreshElementVisible(Element $element): bool {
    while ($element !== null) {
      if (!$element->display) {
        return false;
      }
      $element = $element->ancestor;
    }
    return true;
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

  public static function getRelativePos(int $referenceId, Element $element, ?int &$x, ?int &$y): void {
    $x ??= 0;
    $y ??= 0;
    if ($element->id == $referenceId) {
      return;
    }
    $x += $element->geometry->x;
    $y += $element->geometry->y;
    static::getRelativePos($referenceId, $element->ancestor, $x, $y);
  }

  public static function getRenderedRelativePos(int $referenceId, Element $element, ?int &$x, ?int &$y): void {
    $x ??= 0;
    $y ??= 0;
    if ($element->id == $referenceId) {
      return;
    }
    $x += $element->geometry->x;
    $y += $element->geometry->y;
    if ($element->ancestor !== null) {
      $x -= $element->ancestor->scrollX;
      $y -= $element->ancestor->scrollY;
    }
    static::getRenderedRelativePos($referenceId, $element->ancestor, $x, $y);
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
