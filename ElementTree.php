<?php

namespace SPTK;

trait ElementTree {

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
    $this->changed = true;
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
    $this->changed = true;
  }

  public function setText($text) {
    $this->clear();
    $this->addText($text);
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

  public function findAncestorByType($type) {
    if ($this->type == $type) {
      return $this;
    }
    return $this->ancestor->findAncestorByType($type);
  }

  public function nthChild($n) {
    if (isset($this->descendants[$n])) {
      return $this->descendants[$n];
    }
    return false;
  }

}
