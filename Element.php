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
  protected $events = [];
  protected $attributes = [];
  protected $childClass = [];
  protected $scrollX = 0;
  protected $scrollY = 0;
  protected $changed = false;
  protected $clipped = false;

  public function __construct(?Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    $this->id = self::getNextId();
    if ($ancestor !== null) {
      $this->renderer = $ancestor->renderer;
    }
    if ($type !== null) {
      $this->type = $type;
    } else {
      $this->type = basename(str_replace('\\', '/', get_class($this)));
    }
    if ($name === null) {
      $this->name = StyleSheet::ANY;
    } else {
      $this->name = $name;
    }
    if ($class !== null) {
      $class = preg_replace('/ +/', ' ', $class);
      $class = explode(' ', $class);
      $this->sclass = $class;
    }
    if ($ancestor !== null && !empty($ancestor->childClass)) {
      $this->sclass = array_merge($this->sclass, $ancestor->childClass);
    }
    $this->ancestor = $ancestor;
    $this->geometry = new Geometry($this->ancestor->geometry ?? null);
    $this->recalculateStyle();
    if ($this->ancestor === null) {
      if (self::$root !== null) {
        throw new \Exception("You have to define only one root element.");
      }
      self::$root = $this;
    } else {
      $this->ancestor->addDescendant($this);
    }
    $this->init();
  }

}
