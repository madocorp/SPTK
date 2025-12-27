<?php

namespace SPTK;

class Element {

  use ElementStatic;

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
  protected $cursor = false;
  protected $scrollX = 0;
  protected $scrollY = 0;
  protected $maxX = 0;
  protected $maxY = 0;

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
    $this->cursor = new Cursor();
    $this->ancestor = $ancestor;
    $this->recalculateStyle();
    $this->geometry = new Geometry;
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

  protected function init() {
    ;
  }

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
    foreach ($this->descendants as $descendant) {
      $descendant->recalculateStyle();
    }
    $this->cursor->configure($this->style);
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->setDerivedSize();
    foreach ($this->descendants as $element) {
      $element->measure();
    }
  }

  protected function layout() {
    $this->cursor->reset();
    $this->maxX = 0;
    $this->maxY = 0;
    foreach ($this->descendants as $element) {
      $element->layout();
      $this->maxX = max($this->maxX, $element->geometry->x + $element->geometry->width);
      $this->maxY = max($this->maxY, $element->geometry->y + $element->geometry->height);
    }
    $position = $this->style->get('position');
    $this->geometry->setContentDependentValues($this->maxX, $this->maxY);
    if ($position == 'inline') {
      $this->geometry->setAscent($this->style, $this->cursor->firstLineAscent);
    }
    $this->geometry->setDerivedSize();
    if ($position == 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    } else if ($position == 'inline' || $position == 'newline') {
      $this->geometry->setInlinePosition($this->ancestor->cursor, $this, $this->ancestor->geometry, $position);
    }
    $this->geometry->formatRow($this->cursor, $this->geometry);
    if ($this->geometry->sizeChanged()) {
      $this->draw();
    }
  }

  public function recalculateGeometry() {
    $this->measure();
    $this->layout();
  }

  public function isWord() {
    return false;
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
        if ($descendant->geometry->x - $this->scrollX > $this->geometry->width) {
          continue;
        }
        if ($descendant->geometry->y - $this->scrollY > $this->geometry->height) {
          continue;
        }
        if ($descendant->geometry->x + $descendant->geometry->width - $this->scrollX < 0) {
          continue;
        }
        if ($descendant->geometry->y + $descendant->geometry->height - $this->scrollY < 0) {
          continue;
        }
      $dTexture = $descendant->render();
      if ($dTexture !== false) {
        $dTexture->copyTo($tmpTexture, $descendant->geometry->x - $this->scrollX, $descendant->geometry->y - $this->scrollY);
      }
    }
    new Border($tmpTexture, $this->geometry, $this->ancestor->geometry, $this->style);
    if ($this->style->get('scrollable')) {
      new Scrollbar($tmpTexture, $this->scrollX, $this->scrollY, $this->maxX, $this->maxY, $this->geometry, $this->style);
    }
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

  protected function removeDescendant($element) {
    foreach ($this->descendants as $i => $descendant) {
      if ($element->id === $descendant->id) {
        unset($this->descendants[$i]);
        $this->descendants = array_values($this->descendants);
        break;
      }
    }
    foreach ($this->stack as $i => $descendant) {
      if ($element->id === $descendant->id) {
        unset($this->stack[$i]);
        $this->stack = array_values($this->stack);
        break;
      }
    }
  }

  public function remove() {
    $this->ancestor->removeDescendant($this);
  }

  public function moveAfter($element) {
    if ($element->id === $this->id) {
      return;
    }
    $after = false;
    $ancestor = $this->ancestor;
    foreach ($ancestor->descendants as $i => $item) {
      if ($item->id === $element->id) {
        $after = $i;
      } else if ($item->id == $this->id) {
        $moveFrom = $i;
      }
    }
    if ($after === false) {
      return;
    }
    array_splice($ancestor->descendants, $moveFrom, 1);
    if ($moveFrom < $after) {
      $after--;
    }
    array_splice($ancestor->descendants, $after + 1, 0, [$this]);
  }

  public function clear() {
    $this->descendants = [];
    $this->stack = [];
  }

  public function raise() {
    foreach ($this->ancestor->stack as $i => $element) {
      if ($element->id === $this->id) {
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
      if ($element->id === $this->id) {
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

  public function getId() {
    return $this->id;
  }

  public function getName() {
    return $this->name;
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

  public function addText($text) {
    $rows = explode("\n", $text);
    foreach ($rows as $i => $row) {
      if ($i > 0) {
        new Element($this, false, false, 'NL');
      }
      $row = explode(' ', $row);
      foreach ($row as $word) {
        $element = new Word($this);
        $element->setValue($word);
      }
    }
  }

  public function getText(&$text = null) {
    if ($text === null) {
      $text = [];
    }
    foreach ($this->descendants as $descendant) {
      if ($descendant->type === 'Word') {
        $text[] = $descendant->getValue();
      } else {
        $descendant->getText($text);
      }
    }
    return implode(' ', $text);
  }

  public function show() {
    $this->display = true;
  }

  public function hide() {
    $this->display = false;
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
    echo "{$pad}{$this->type}@{$this->id}" . ($this->name !== 0 ? "#{$this->name}" : '') ."{$class}{$value}";
    echo "  {$this->geometry->width}x{$this->geometry->height} {$this->geometry->x}:{$this->geometry->y}\n";
    foreach ($this->events as $event => $handler) {
      echo "{$pad}  - {$event} > " . (is_array($handler) ? (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1] : implode('::', $handler)) . "\n";
    }
    foreach ($this->descendants as $element) {
      $element->debug($level + 1);
    }
  }

}
