<?php

namespace SPTK;

trait ElementStyle {

  protected function recalculateStyle() {
    $defaultStyle = false;
    $ancestorStyle = false;
    if (isset(self::$root)) {
      $defaultStyle = self::$root->style;
      $ancestorStyle = $this->ancestor->style;
    }
    $this->style = StyleSheet::get($defaultStyle, $ancestorStyle, $this->type, $this->sclass, $this->name);
    if (!$this->style->get('display')) {
      $this->display = false;
    }
    $this->styleChanged = true;
    foreach ($this->descendants as $descendant) {
      $descendant->recalculateStyle();
    }
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
    }
  }

  public function removeClass($class, $dynamic = false) {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    $key = array_search($class, $this->sclass);
    if ($key !== false) {
      unset($this->sclass[$key]);
      $this->recalculateStyle();
    }
  }

  public function hasClass($class, $dynamic = false) {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    return in_array($class, $this->sclass);
  }

  public function addChildClass($class) {
    array_push($this->childClass, $class);
  }

  public function removeChildClass($class) {
    array_pop($this->childClass);
  }

}
