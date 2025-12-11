<?php

namespace SPTK;

class Cursor {

  public $elements = [];
  public $x = 0;
  public $y = 0;
  public $w = 0;
  public $s = 0;
  public $ascent = 0;
  public $descent = 0;
  public $firstLineAscent = false;
  public $textAlign;
  public $wordSpacing;
  public $lineHeight;
  public $previousIsWord = false;
  public $lines = 0;

  public function configure($style) {
    $this->wordSpacing = $style->get('wordSpacing');
    $fontSize = $style->get('fontSize');
    $this->lineHeight = $style->get('lineHeight', $fontSize);
    $this->textAlign = $style->get('textAlign');
  }

  public function reset() {
    $this->elements = [];
    $this->x = 0;
    $this->y = 0;
    $this->w = 0;
    $this->s = 0;
    $this->ascent = 0;
    $this->descent = 0;
    $this->firstLineAscent = false;
    $this->previousIsWord = false;
    $this->lines = 0;
  }

  public function addElement($element, $geometry, $isWord) {
    $this->elements[] = $element;
    $this->ascent = max($this->ascent, $geometry->ascent);
    $this->descent = max($this->descent, $geometry->descent);
    $this->x += $geometry->fullWidth;
    $this->w += $geometry->fullWidth;
    if ($isWord && $this->previousIsWord) {
      $this->x += $this->wordSpacing;
      $this->s++;
    }
    $this->previousIsWord = $isWord;
    if ($this->lines <= 1) {
      $this->lines = 1;
      $this->firstLineAscent = $this->ascent;
    }
  }

  public function newLine() {
    $this->elements = [];
    $this->x = 0;
    $this->y += max($this->lineHeight, $this->ascent + $this->descent);
    $this->w = 0;
    $this->s = 0;
    $this->ascent = 0;
    $this->descent = 0;
    $this->previousIsWord = false;
    $this->lines++;
  }

}
