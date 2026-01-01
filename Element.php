<?php

namespace SPTK;

class Element {

  use ElementStatic;
  use ElementAssistant;
  use ElementLayout;
  use ElementTree;
  use ElementStyle;
  use ElementEvent;

  protected $id;
  protected $name;
  protected $type;
  protected $sclass = [];
  protected $ancestor;
  protected $descendants = [];
  protected $stack = [];
  protected $acceptInput = false;
  protected $display = true;
  protected $renderer = false;
  protected $texture = false;
  protected $value = false;
  protected $geometry = false;
  protected $style = false;
  protected $styleChanged = false;
  protected $events = [];
  protected $attributes = [];
  protected $childClass = [];
  protected $scrollX = 0;
  protected $scrollY = 0;
  protected $clipped = false;

  public function __construct($ancestor = null, $name = false, $class = false, $type = false) {
    $this->id = self::getNextId();
    if (!is_null($ancestor)) {
      $this->renderer = $ancestor->renderer;
    }
    if ($type) {
      $this->type = $type;
    } else {
      $this->type = basename(str_replace('\\', '/', get_class($this)));
    }
    if ($name === false) {
      $this->name = StyleSheet::ANY;
    } else {
      $this->name = $name;
    }
    if ($class !== false) {
      $class = preg_replace('/ +/', ' ', $class);
      $class = explode(' ', $class);
      $this->sclass = $class;
    }
    if (!is_null($ancestor) && !empty($ancestor->childClass)) {
      $this->sclass = array_merge($this->sclass, $ancestor->childClass);
    }
    $this->ancestor = $ancestor;
    $this->geometry = new Geometry;
    $this->recalculateStyle();
    if (is_null($this->ancestor)) {
      if (!is_null(self::$root)) {
        throw new \Exception("You have to define only one root element.");
      }
      self::$root = $this;
    } else {
      $this->ancestor->addDescendant($this);
    }
    $this->init();
  }

}
