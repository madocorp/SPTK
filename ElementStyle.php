<?php

namespace SPTK;

trait ElementStyle {

  public function recalculateStyle(): void {
    $defaultStyle = null;
    $ancestorStyle = null;
    if (isset(self::$root)) {
      $defaultStyle = self::$root->style;
      $ancestorStyle = $this->ancestor->style;
    }
    $this->style = StyleSheet::get($defaultStyle, $ancestorStyle, $this->type, $this->sclass, $this->name);
    if (!$this->style->get('display')) {
      $this->display = false;
    }
    $this->changed = true;
    foreach ($this->descendants as $descendant) {
      $descendant->recalculateStyle();
    }
  }

  public function addClass(string $class, bool $dynamic = false): void {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
    }
  }

  public function removeClass(string $class, bool $dynamic = false): void {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
      $this->recalculateStyle();
    }
  }

  public function hasClass(string $class, bool $dynamic = false): bool {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    return in_array($class, $this->sclass);
  }

  public function addChildClass(string $class): void {
    array_push($this->childClass, $class);
  }

  public function removeChildClass(string $class): void {
    array_pop($this->childClass);
  }

}
