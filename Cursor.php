<?php

namespace SPTK;

class Cursor {

  public $elements;
  public $n;
  public $i = 0;
  public $x = 0;
  public $y = 0;
  public $w = 0;
  public $s = 0;
  public $lineFirstElement = 0;
  public $lineLastElement = 0;
  public $ascent = 0;
  public $descent = 0;
  public $firstLineAscent = false;
  public $lineElements = 0;
  public $separators = 0;
  public $wordSpacing;
  public $lineHeight;
  protected $previousIsWord = false;

  public function __construct($elements, $wordSpacing, $lineHeight) {
    $this->elements = $elements;
    $this->n = count($this->elements);
    $this->wordSpacing = $wordSpacing;
    $this->lineHeight = $lineHeight;
  }

  public function skipElement() {
    $this->i++;
    if ($this->i >= $this->n) {
      return true;
    }
    return false;
  }

  public function addElement($w, $h, $ascent, $descent, $isWord) {
    $this->lineElements++;
    $this->lineLastElement++;
    $this->ascent = max($this->ascent, $ascent);
    $this->descent = max($this->descent, $descent);
    $this->x += $w;
    $this->w += $w;
    if ($isWord && $this->previousIsWord) {
      $this->x += $this->wordSpacing;
      $this->s++;
    }
    $this->previousIsWord = $isWord;
    $this->i++;
    if ($this->i >= $this->n) {
      return true;
    }
    return false;
  }

  public function endLine() {
    if ($this->firstLineAscent === false) {
      $this->firstLineAscent = $this->ascent;
    }
    $this->y += max($this->lineHeight, $this->ascent + $this->descent);
    $this->separators = 0;
    $this->lineFirstElement += $this->lineElements;
    $this->lineLastElement = $this->lineFirstElement;
    $this->x = 0;
    $this->w = 0;
    $this->s = 0;
    $this->lineElements = 0;
    $this->previousIsWord = false;
    $this->ascent = 0;
    $this->descent = 0;
  }

}
