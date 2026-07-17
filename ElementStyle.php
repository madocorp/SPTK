<?php

namespace SPTK;

trait ElementStyle {

  public function recalculateStyle(): void {
    $this->recalculateOwnStyle();
    foreach ($this->descendants as $descendant) {
      $descendant->recalculateStyle();
    }
  }

  protected function recalculateOwnStyle(): void {
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
  }

  public function addClass(string $class): void {
    $class = $this->className($class, false);
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
    }
  }

  public function addVariant(string $class): void {
    $class = $this->className($class, true);
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
    }
  }

  public function removeClass(string $class): void {
    $class = $this->className($class, false);
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
      $this->recalculateStyle();
    }
  }

  public function removeVariant(string $class): void {
    $class = $this->className($class, true);
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
      $this->recalculateStyle();
    }
  }

  public function hasClass(string $class): bool {
    $class = $this->className($class, false);
    return in_array($class, $this->sclass);
  }

  public function hasVariant(string $class): bool {
    $class = $this->className($class, true);
    return in_array($class, $this->sclass);
  }

  private function className(string $class, bool $variant): string {
    return $variant ? $this->variantName($class) : $class;
  }

  private function variantName(string $variant): string {
    return $this->type . ':' . $variant;
  }

  public function addChildClass(string $class): void {
    array_push($this->childClass, $class);
  }

  public function removeChildClass(string $class): void {
    array_pop($this->childClass);
  }

}
