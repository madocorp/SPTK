<?php

namespace SPTK;

trait ElementAssistant {

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

