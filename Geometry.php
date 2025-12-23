<?php

namespace SPTK;

class Geometry {

  public $x = 0;
  public $y = 0;
  public $width = 0;
  public $height = 0;
  public $innerWidth = 0;
  public $innerHeight = 0;
  public $fullWidth = 0;
  public $fullHeight = 0;
  public $contentWidth = 0;
  public $contentHeight = 0;
  public $originalWidth = 0;
  public $originalHeight = 0;
  public $marginTop = 0;
  public $marginLeft = 0;
  public $marginBottom = 0;
  public $marginRight = 0;
  public $borderTop = 0;
  public $borderLeft = 0;
  public $borderBottom = 0;
  public $borderRight = 0;
  public $paddingTop = 0;
  public $paddingLeft = 0;
  public $paddingBottom = 0;
  public $paddingRight = 0;
  public $ascent = 0;
  public $descent = 0;

  public function setValues($ancestorGeometry, $style) {
    $this->originalWidth = $this->width;
    $this->originalHeight = $this->height;
    $this->marginTop = $style->get('marginTop', $ancestorGeometry->innerWidth);
    $this->marginLeft = $style->get('marginLeft', $ancestorGeometry->innerHeight);
    $this->marginBottom = $style->get('marginBottom', $ancestorGeometry->innerWidth);
    $this->marginRight = $style->get('marginRight', $ancestorGeometry->innerHeight);
    $this->borderTop = $style->get('borderTop', $ancestorGeometry->innerWidth);
    $this->borderLeft = $style->get('borderLeft', $ancestorGeometry->innerHeight);
    $this->borderBottom = $style->get('borderBottom', $ancestorGeometry->innerWidth);
    $this->borderRight = $style->get('borderRight', $ancestorGeometry->innerHeight);
    $this->paddingTop = $style->get('paddingTop', $ancestorGeometry->innerWidth);
    $this->paddingLeft = $style->get('paddingLeft', $ancestorGeometry->innerHeight);
    $this->paddingBottom = $style->get('paddingBottom', $ancestorGeometry->innerWidth);
    $this->paddingRight = $style->get('paddingRight', $ancestorGeometry->innerHeight);
    $this->width = $style->get('width', $ancestorGeometry->innerWidth);
    $this->height = $style->get('height', $ancestorGeometry->innerHeight);
  }

  public function setContentDependentValues($maxX, $maxY) {
    $this->contentWidth = $maxX + $this->paddingRight + $this->borderRight;
    if ($this->width === 'content') {
      $this->width = $this->contentWidth;
    }
    $this->contentHeight = $maxY + $this->paddingBottom + $this->borderBottom;
    if ($this->height === 'content') {
      $this->height = $this->contentHeight;
    }
    if ($this->marginTop === 'half') {
      $this->marginTop = (int)(-$this->height / 2);
    }
    if ($this->marginLeft === 'half') {
      $this->marginLeft = (int)(-$this->width / 2);
    }
    if ($this->marginBottom === 'half') {
      $this->marginBottom = (int)(-$this->height / 2);
    }
    if ($this->marginRight === 'half') {
      $this->marginRight = (int)(-$this->width / 2);
    }
  }

  public function setDerivedSize() {
    if ($this->width != 'content') {
      $this->innerWidth =
        $this->width -
        $this->borderLeft -
        $this->borderRight -
        $this->paddingLeft -
        $this->paddingRight;
      $this->fullWidth =
        $this->width +
        (is_int($this->marginLeft) && $this->marginLeft > 0 ? $this->marginLeft : 0) +
        (is_int($this->marginRight) && $this->marginRight > 0 ? $this->marginRight : 0);
    }
    if ($this->height != 'content') {
      $this->innerHeight =
        $this->height -
        $this->borderTop -
        $this->borderBottom -
        $this->paddingTop -
        $this->paddingBottom;
      $this->fullHeight =
        $this->height +
        (is_int($this->marginTop) && $this->marginTop > 0 ? $this->marginTop : 0) +
        (is_int($this->marginBottom) && $this->marginBottom > 0 ? $this->marginBottom : 0);
    }
  }

  public function setAscent($style, $firstLineAscent) {
    $ascent = $style->get('ascent', $this->height);
    if ($ascent == 'auto') {
      if ($firstLineAscent === false) {
        $this->ascent = $this->fullHeight ;
        $this->descent = 0;
      } else {
        $this->ascent = $firstLineAscent + $this->marginTop + $this->borderTop + $this->paddingTop;
        $this->descent = $this->fullHeight - $this->ascent;
      }
    } else if ($ascent == 'content') {
      $this->ascent = $this->fullHeight;
      $this->descent = 0;
    } else {
      $this->ascent = $ascent + $this->marginTop;
      $this->descent = $this->fullHeight - $this->ascent;
    }
  }

  public function setAbsolutePosition($ancestorGeometry, $style) {
    $this->x = $style->get('x', $ancestorGeometry->innerWidth, $isNegative);
    if ($isNegative) {
      $this->x = $ancestorGeometry->width - $ancestorGeometry->paddingRight - $ancestorGeometry->borderRight - $this->fullWidth + $this->x - $this->marginRight;
    } else {
      $this->x = $this->x + $this->marginLeft + $ancestorGeometry->paddingLeft + $ancestorGeometry->borderLeft;
    }
    $this->y = $style->get('y', $ancestorGeometry->innerHeight, $isNegative);
    if ($isNegative) {
      $this->y = $ancestorGeometry->height - $ancestorGeometry->paddingBottom - $ancestorGeometry->borderBottom - $this->fullHeight + $this->y - $this->marginBottom;
    } else {
      $this->y = $this->y + $this->marginTop + $ancestorGeometry->paddingTop + $ancestorGeometry->borderTop;
    }
  }

  public function setInlinePosition($cursor, $element, $ancestorGeometry, $position) {
    if (
      $position == 'newline' ||
      (
        $cursor->textWrap == 'auto' &&
        $ancestorGeometry->width != 'content' &&
        $cursor->x + ($element->isWord() && count($cursor->elements) > 0 ? $cursor->wordSpacing : 0) + $this->width > $ancestorGeometry->innerWidth
      )
    ) {
      $this->formatRow($cursor, $ancestorGeometry);
      $cursor->newLine();
    }
    $cursor->addElement($element, $this);
  }

  public function formatRow($cursor, $ancestorGeometry) {
    if (count($cursor->elements) <= 0) {
      return;
    }
    switch ($cursor->textAlign) {
      case 'left':
        $this->alignLeft($cursor, $ancestorGeometry);
        break;
      case 'right':
        $this->alignRight($cursor, $ancestorGeometry);
        break;
      case 'justify':
        $this->alignJustify($cursor, $ancestorGeometry);
        break;
      case 'center':
        $this->alignCenter($cursor, $ancestorGeometry);
        break;
    }
  }

  protected function alignLeft($cursor, $ancestorGeometry) {
    $x = $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    foreach ($cursor->elements as $element) {
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $element->isWord();
      if ($isWord && $previousIsWord) {
        $x += $cursor->wordSpacing;
      }
      $geometry->x = $x + $geometry->marginLeft;
      $geometry->y = $y + $cursor->y + $cursor->ascent - $geometry->ascent + $geometry->marginTop;
      $x += $geometry->fullWidth;
      $previousIsWord = $isWord;
    }
  }

  protected function alignRight($cursor, $ancestorGeometry) {
    $x = $ancestorGeometry->width - $ancestorGeometry->borderRight - $ancestorGeometry->paddingRight;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    $cursor->elements = array_reverse($cursor->elements);
    foreach ($cursor->elements as $element) {
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $element->isWord();
      if ($isWord && $previousIsWord) {
        $x -= $cursor->wordSpacing;
      }
      $x -= $geometry->fullWidth;
      $geometry->x = $x;
      $geometry->y = $y + $cursor->y + $cursor->ascent - $geometry->ascent + $geometry->marginTop;
      $previousIsWord = $isWord;
    }
  }

  protected function alignJustify($cursor, $ancestorGeometry) {
    $spaceWidth = 0;
    if ($cursor->s > 0) {
      $spaceWidth = (int)(($ancestorGeometry->innerWidth - $cursor->w) / $cursor->s);
    }
    $x = $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    foreach ($cursor->elements as $element) {
      $geometry = $element->getGeometry();
      if ($previousIsWord) {
        $x += $spaceWidth;
      }
      $geometry->x = $x + $geometry->marginLeft;
      $geometry->y = $y + $cursor->y + $cursor->ascent - $geometry->ascent + $geometry->marginTop;
      $x += $geometry->fullWidth;
      $previousIsWord = true;
    }
  }

  protected function alignCenter($cursor, $ancestorGeometry) {
    $lw = $cursor->x;
    $x = (int)(($ancestorGeometry->innerWidth - $lw) / 2) + $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    foreach ($cursor->elements as $element) {
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $element->isWord();
      if ($isWord && $previousIsWord) {
        $x += $cursor->wordSpacing;
      }
      $geometry->x = $x + $geometry->marginLeft;
      $geometry->y = $y + $cursor->y + $cursor->ascent - $geometry->ascent + $geometry->marginTop;
      $x += $geometry->fullWidth;
      $previousIsWord = $isWord;
    }
  }

  public function sizeChanged() {
    return ($this->originalWidth !== $this->width || $this->originalHeight !== $this->height);
  }

}
