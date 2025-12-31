<?php

namespace SPTK;

class Element {

  use ElementStatic;
  use ElementAssistant;

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
  }

  public function recalculateGeometry() {
    $this->measure();
    $this->calculateWidths();
    $this->calculateHeights();
    $this->layout();
    $this->redraw();
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateWidths() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateWidths();
    }
    if ($this->geometry->width === 'content') {
      $width = 0;
      $spaceCount = 0;
      $previousIsWord = false;
      foreach ($this->descendants as $descendant) {
        if ($descendant->display === false) {
          continue;
        }
        if ($descendant->geometry->position === 'inline') {
          if ($descendant->isWord()) {
            if ($previousIsWord) {
              $spaceCount++;
            }
            $previousIsWord = true;
          } else {
            $previousIsWord = false;
          }
          $width += $descendant->geometry->fullWidth;
        }
      }
      $this->geometry->width =
        $this->geometry->borderLeft +
        $this->geometry->paddingLeft +
        $width +
        $spaceCount * $this->geometry->wordSpacing +
        $this->geometry->paddingRight +
        $this->geometry->borderRight;
      $this->geometry->setDerivedWidths();
    }
  }

  protected function calculateHeights() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $lines = [];
    $line = ['ascent' => 0, 'descent' => 0, 'width' => 0, 'spaceCount' => 0, 'elements' => []];
    $lineWidth = 0;
    $previousIsWord = false;
    foreach ($this->descendants as $descendant) {
      if ($descendant->geometry->position === 'absolute') {
        continue;
      }
      if ($descendant->display === false) {
        continue;
      }
      $space = 0;
      if ($descendant->isWord()) {
        if ($previousIsWord) {
          $space = $this->geometry->wordSpacing;
        }
        $previousIsWord = true;
      } else {
        $previousIsWord = false;
      }
      if (
        (
          $this->geometry->textWrap === 'auto' &&
          $line['width'] > 0 &&
          $lineWidth + $space + $descendant->geometry->fullWidth > $this->geometry->innerWidth
        ) ||
        $descendant->type === 'NL'
      ) {
        if ($line['ascent'] + $line['descent'] < $this->geometry->lineHeight) {
          $line['descent'] = $this->geometry->lineHeight - $line['ascent'];
        }
        $lines[] = $line;
        $line = ['ascent' => 0, 'descent' => 0, 'width' => 0, 'spaceCount' => 0, 'elements' => []];
        $lineWidth = 0;
        $space = 0;
      }
      $lineWidth += $space;
      $lineWidth += $descendant->geometry->fullWidth;
      $line['elements'][] = $descendant;
      $line['ascent'] = max($line['ascent'], $descendant->geometry->ascent);
      $line['descent'] = max($line['descent'], $descendant->geometry->descent);
      $line['width'] += $descendant->geometry->fullWidth;
      $line['spaceCount'] += ($space > 0 ? 1 : 0);
    }
    if (count($line['elements']) > 0) {
      if ($line['ascent'] + $line['descent'] < $this->geometry->lineHeight) {
        $line['descent'] = $this->geometry->lineHeight - $line['ascent'];
      }
      $lines[] = $line;
    }
    $this->geometry->lines = $lines;
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent);
  }

  protected function layout() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
    $y = $this->geometry->borderTop + $this->geometry->paddingTop;
    $space = $this->geometry->wordSpacing;
    $maxX = 0;
    foreach ($this->geometry->lines as $line) {
      $y += $line['ascent'];
      $x = $this->geometry->borderLeft + $this->geometry->paddingLeft;
      if ($this->geometry->textAlign === 'right') {
        $x += $this->geometry->innerWidth - $line['width'] - $line['spaceCount'] * $space;
      } else if ($this->geometry->textAlign === 'center') {
        $x += (int)(($this->geometry->innerWidth - $line['width'] - $line['spaceCount'] * $space) / 2);
      } if ($this->geometry->textAlign === 'justify') {
        if ($line['spaceCount'] > 0) {
          $space = (int)(($this->geometry->innerWidth - $line['width']) / $line['spaceCount']);
        } else {
          $space = 0;
        }
      }
      $previousIsWord = false;
      foreach ($line['elements'] as $element) {
        $element->geometry->y = $y - $element->geometry->ascent + $element->geometry->marginTop;
        if ($element->isWord()) {
          if ($previousIsWord) {
            $x += $space;
          }
          $previousIsWord = true;
        }
        $element->geometry->x = $x + $element->geometry->marginLeft;
        $x += $element->geometry->fullWidth;
        $maxX = max($maxX, $x);
      }
      $y += $line['descent'];
    }
    $this->geometry->contentWidth = $maxX;
  }

  protected function redraw($force = false) {
    if ($this->display === false) {
      return;
    }
    if ($force || $this->geometry->sizeChanged()) {
      $this->draw();
    }
    foreach ($this->stack as $i => $element) {
      $element->redraw($force);
    }
  }

  protected function draw() {
    $color = $this->style->get('backgroundColor');
    $width = $this->geometry->width;
    $height = $this->geometry->height;
    $this->texture = new Texture($this->renderer, $width, $height, $color);
  }

  protected function render() {
    if ($this->display === false) {
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
      if ($descendant->display === false) {
        continue;
      }
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
      new Scrollbar($tmpTexture, $this->scrollX, $this->scrollY, $this->geometry->contentWidth, $this->geometry->contentHeight, $this->geometry, $this->style);
    }
    return $tmpTexture;
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
      $this->redraw(true);
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
      $this->redraw(true);
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

  public function isWord() {
    return false;
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

  public function addEvent($event, $handler) {
    if (!is_array($handler)) {
      $handler = preg_split('/::/', $handler);
    }
    $this->events[$event] = $handler;
  }

  public function removeEvent($event) {
    unset($this->events[$event]);
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

}
