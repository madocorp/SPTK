<?php

namespace SPTK;

trait ElementTree {

  public function addDescendant(Element $element): void {
    $this->descendants[] = $element;
    $this->stack[] = $element;
    if ($element->ancestor !== $this) {
      $element->ancestor = $this;
    }
  }

  public function removeDescendant(Element $element): void {
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

  public function remove(): void {
    $this->ancestor->removeDescendant($this);
  }

  public function clear(): void {
    $this->descendants = [];
    $this->stack = [];
    $this->changed = true;
  }

  public function raise(): void {
    $this->raiseLocal();
    $this->ancestor->raise();
  }

  protected function raiseLocal(): void {
    foreach ($this->ancestor->stack as $i => $element) {
      if ($element->id === $this->id) {
        unset($this->ancestor->stack[$i]);
        $this->ancestor->stack[] = $element;
        $this->ancestor->stack = array_values($this->ancestor->stack);
        break;
      }
    }
  }

  public function lower(): void {
    foreach ($this->ancestor->stack as $i => $element) {
      if ($element->id === $this->id) {
        unset($this->ancestor->stack[$i]);
        array_unshift($this->ancestor->stack, $element);
        $this->ancestor->stack = array_values($this->ancestor->stack);
        return;
      }
    }
  }

  public function addText(string $text): void {
    $rows = explode("\n", $text);
    foreach ($rows as $i => $row) {
      if ($i > 0) {
        new Element($this, null, null, 'NL');
      }
      $row = explode(' ', $row);
      foreach ($row as $i => $word) {
        if ($i !== 0) {
          new Elements\Space($this);
        }
        $element = new Elements\Word($this);
        $element->setValue($word);
      }
    }
    $this->changed = true;
  }

  public function setText(string $text): void {
    $this->clear();
    $this->addText($text);
  }

  public function getText(?array &$text = null): string {
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

  public function moveAfter(Element $element): void {
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

  public function findAncestorByType(string $type): Element|false {
    if ($this->type == $type) {
      return $this;
    }
    if ($this->ancestor === null) {
      return false;
    }
    return $this->ancestor->findAncestorByType($type);
  }

  public function nthChild(int $n): Element|false {
    if (isset($this->descendants[$n])) {
      return $this->descendants[$n];
    }
    return false;
  }

  public function countDescendants(): int {
    return count($this->descendants);
  }

}
