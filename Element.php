<?php

namespace SPTK;

class Element {

  use ElementStatic;

  protected $iid;
  protected $id;
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
  protected $cursor = false;

  public function __construct($ancestor = null, $id = false, $class = false, $type = false) {
    $this->iid = self::getNextId();
    if (!is_null($ancestor)) {
      $this->renderer = $ancestor->renderer;
    }
    if ($type) {
      $this->type = $type;
    } else {
      $this->type = basename(str_replace('\\', '/', get_class($this)));
    }
    if ($id === false) {
      $this->id = StyleSheet::ANY;
    } else {
      $this->id = $id;
    }
    if ($class !== false) {
      $class = preg_replace('/ +/', ' ', $class);
      $class = explode(' ', $class);
      $this->sclass = $class;
    }
    if (!is_null($ancestor) && !empty($ancestor->childClass)) {
      $this->sclass = array_merge($this->sclass, $ancestor->childClass);
    }
    if ($this->id !== StyleSheet::ANY && isset(self::$elementsById[$this->id])) {
      throw new \Exception("Duplicated element id: {$this->id}");
    }
    $this->cursor = new Cursor();
    $this->ancestor = $ancestor;
    $this->recalculateStyle();
    $this->geometry = new Geometry($this);
    self::$elementsById[$this->id] = $this;
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

  public function purge() {
    unset(self::$elementsById[$this->id]);
  }

  protected function init() {

  }

  protected function recalculateStyle() {
    $defaultStyle = false;
    $ancestorStyle = false;
    if (isset(self::$root)) {
      $defaultStyle = self::$root->style;
      $ancestorStyle = $this->ancestor->style;
    }
    $this->style = StyleSheet::get($defaultStyle, $ancestorStyle, $this->type, $this->sclass, $this->id);
    if (!$this->style->get('display')) {
      $this->display = false;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->recalculateStyle();
    }
    $this->cursor->configure($this->style);
  }

  protected function calculateGeometry() {
    $this->cursor->reset();
    $originalWidth = $this->geometry->width;
    $originalHeight = $this->geometry->height;
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->setSize($this->ancestor->geometry, $this->style);
    $maxX = 0;
    $maxY = 0;
    foreach ($this->descendants as $element) {
      $element->calculateGeometry();
      $maxX = max($maxX, $element->geometry->x + $element->geometry->width);
      $maxY = max($maxY, $element->geometry->y + $element->geometry->height);
    }
    $this->geometry->formatRow($this->cursor, $this->geometry);
    $position = $this->style->get('position');
    if ($position == 'word') {
      $this->geometry->setCalculatedSize();
    } else {
      $this->geometry->setContentSize($this->style, $maxX, $maxY);
    }
    if ($position == 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    } else if ($position == 'inline' || $position == 'word' || $position == 'newline') {
      $textAlign = $this->ancestor->style->get('textAlign');
      $this->geometry->setInlinePosition($this->ancestor->cursor, $this, $this->ancestor->geometry, $position, $textAlign);
    }
    $this->geometry->setAscent($this->style, $this->cursor->firstLineAscent);
    if ($originalWidth != $this->geometry->width || $originalHeight != $this->geometry->height) {
      $this->draw();
    }
  }

  protected function render() {
    if (!$this->display) {
      return false;
    }
    if ($this->geometry->width == 'content' || $this->geometry->height == 'content') {
      return false;
    }
    $this->draw();
    if ($this->texture === false) {
      return false;
    }
    $tmpTexture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, [0, 0, 0, 0]);
    $this->texture->copyTo($tmpTexture, 0, 0);
    $n = count($this->stack);
    for ($i = 0; $i < $n; $i++) {
      $descendant = $this->stack[$i];
      $dTexture = $descendant->render();
      if ($dTexture !== false) {
        $dTexture->copyTo($tmpTexture, $descendant->geometry->x, $descendant->geometry->y);
      }
    }
    new Border($tmpTexture, $this->geometry, $this->ancestor->geometry, $this->style);
    return $tmpTexture;
  }

  protected function draw() {
    $color = $this->style->get('backgroundColor');
    $width = $this->geometry->width - $this->geometry->borderLeft - $this->geometry->borderRight;
    $height = $this->geometry->height - $this->geometry->borderTop - $this->geometry->borderBottom;
    $this->texture = new Texture($this->renderer, $width, $height, $color);
  }

  public function redraw() {
    $this->draw();
    foreach ($this->stack as $i => $element) {
      $element->redraw();
    }
  }

  protected function addDescendant($element) {
    $this->descendants[] = $element;
    $this->stack[] = $element;
  }

  public function remove() {
    $ancestor = $this->ancestor;
    foreach ($ancestor->descendants as $i => $element) {
      if ($element->iid == $this->iid) {
        unset($ancestor->descendants[$i]);
        $ancestor->descendants = array_values($ancestor->descendants);
        return;
      }
    }
    foreach ($ancestor->stack as $i => $element) {
      if ($element->iid == $this->iid) {
        unset($ancestor->stack[$i]);
        $ancestor->stack = array_values($ancestor->stack);
        return;
      }
    }
  }

  public function raise() {
    foreach ($this->ancestor->stack as $i => $element) {
      if ($element->iid == $this->iid) {
        unset($this->ancestor->stack[$i]);
        $this->ancestor->stack[] = $element;
        $this->ancestor->stack = array_values($this->ancestor->stack);
        break;
      }
    }
    $this->ancestor->raise();
  }

  public function lower() {
    foreach ($this->ancestor->stack as $i => $element) {
      if ($element->iid == $this->iid) {
        unset($this->ancestor->stack[$i]);
        array_unshift($this->ancestor->stack, $element);
        $this->ancestor->stack = array_values($this->ancestor->stack);
        return;
      }
    }
  }

  public function addClass($class, $dynamic = false) {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    if (!in_array($class, $this->sclass)) {
      $this->sclass[] = $class;
      $this->recalculateStyle();
      $this->redraw();
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
      $this->redraw();
    }
  }

  public function hasClass($class, $dynamic = false) {
    if ($dynamic) {
      $class = $this->type . ':' . $class;
    }
    return in_array($class, $this->sclass);
  }

  public function eventHandler($event) {
    if (!$this->display) {
      return false;
    }
    $n = count($this->stack);
    if ($n > 0) {
      for ($i = 0; $i < $n; $i++) {
        $descendant = $this->stack[($n + $i - 1) % $n];
        if ($descendant->display) {
          if ($descendant->eventHandler($event)) {
            return true;
          }
          break;
        }
      }
    }
    if (isset($event['name']) && isset($this->events[$event['name']])) {
      return call_user_func($this->events[$event['name']], $this, $event);
    }
    return false;
  }

  public function getIid() {
    return $this->iid;
  }

  public function getId() {
    return $this->id;
  }

  public function getType() {
    return $this->type;
  }

  public function getClass() {
    return $this->sclass;
  }

  public function getGeometry() {
    return $this->geometry;
  }

  public function getStyle() {
    return $this->style;
  }

  public function getDescendants() {
    return $this->descendants;
  }

  public function getAncestor() {
    return $this->ancestor;
  }

  public function getNext() { // descendants or stack?
    if (empty($this->descendants)) {
      return false;
    }
    return $this->descendants[0];
  }

  public function getAttributeList() {
    return [];
  }

  public function addevent($event, $handler) {
    if (!is_array($handler)) {
      $handler = preg_split('/::/', $handler);
    }
    $this->events[$event] = $handler;
  }

  public function setValue($value) {
    $this->value = $value;
  }

  public function getValue() {
    return $this->value;
  }

  public function show() {
    $this->display = true;
  }

  public function hide() {
    $this->display = false;
  }

  public function debug($level = 0) {
    $pad = str_repeat(' ', $level * 4);
    $class = '';
    if (!empty($this->sclass)) {
      $class = '.' . implode('.', $this->sclass);
    }
    $value = '';
    if ($this->value !== false) {
      $value = " [{$this->value}]";
    }
    echo "{$pad}{$this->type}@{$this->iid}#{$this->id}{$class}{$value}\n";
    foreach ($this->events as $event => $handler) {
      echo "{$pad}  - {$event} > " . (is_array($handler) ? (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1] : implode('::', $handler)) . "\n";
    }
    foreach ($this->descendants as $element) {
      $element->debug($level + 1);
    }
  }

  public function addChildClass($class) {
    array_push($this->childClass, $class);
  }

  public function removeChildClass($class) {
    array_pop($this->childClass);
  }

  public function findAncestorByType($type) {
    if ($this->type == $type) {
      return $this;
    }
    return $this->ancestor->findAncestorByType($type);
  }

}
