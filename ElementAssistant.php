<?php

namespace SPTK;

trait ElementAssistant {

  protected function init() {
    ;
  }

  public function isWord() {
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

  public function scrollToLeft() {
    $this->scrollX = 0;
  }

  public function scrollToRight() {
    if ($this->geometry->contentWidth > $this->geometry->innerWidth) {
      $this->scrollX = $this->geometry->contentWidth - $this->geometry->innerWidth;
    } else {
      $this->scrollX = 0;
    }
  }

  public function scrollToTop() {
    $this->scollY = 0;
  }

  public function scrollToBottom() {
    // todo
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

