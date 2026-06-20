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

  public function addClass(string $class, bool $variant = false): void {
    $class = $this->className($class, $variant);
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
    }
  }

  public function addVariant(string $variant): void {
    $this->addClass($variant, variant: true);
  }

  public function removeClass(string $class, bool $variant = false): void {
    $class = $this->className($class, $variant);
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
      $this->recalculateStyle();
    }
  }

  public function removeVariant(string $variant): void {
    $this->removeClass($variant, variant: true);
  }

  public function hasClass(string $class, bool $variant = false): bool {
    $class = $this->className($class, $variant);
    return in_array($class, $this->sclass);
  }

  public function hasVariant(string $variant): bool {
    return $this->hasClass($variant, variant: true);
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
