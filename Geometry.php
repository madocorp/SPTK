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
public $baseLine = 0; // ???

  public function setValues($ancestorGeometry, $style) {
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
  }

  public function setSize($ancestorGeometry, $style) {
    if ($style->get('display') != 'word') {
      $this->width = $style->get('width', $ancestorGeometry->innerWidth);
      $this->height = $style->get('height', $ancestorGeometry->innerHeight);
    }
    $this->setInnerSize();
  }

  public function setContentSize($style, $maxX, $maxY) {
    $this->contentWidth = $maxX + 1 + $this->borderRight + $this->paddingRight;
    if ($style->get('width') == 'content') {
      $this->width = $this->contentWidth;
    }
    $this->contentHeight = $maxY + 1 + $this->borderBottom + $this->paddingBottom;
    if ($style->get('height') == 'content') {
      $this->height = $this->contentHeight;
    }
    if ($this->marginTop == 'half') {
      $this->marginTop = (int)(-$this->height / 2);
    }
    if ($this->marginLeft == 'half') {
      $this->marginLeft = (int)(-$this->width / 2);
    }
    if ($this->marginBottom == 'half') {
      $this->marginBottom = (int)(-$this->height / 2);
    }
    if ($this->marginRight == 'half') {
      $this->marginRight = (int)(-$this->width / 2);
    }
    $this->setInnerSize();
  }

  public function setInnerSize() {
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
    $ascent = $style->get('ascent', $this->innerHeight);
    if ($ascent == 'auto') {
      if ($firstLineAscent === false) {
        $this->ascent = $this->height;
        $this->descent = 0;
      } else {
        $this->ascent = $firstLineAscent;
        $this->descent = $this->height - $firstLineAscent;
      }
    } else {
      $this->ascent = $ascent;
      $this->descent = $this->height - $ascent;
    }
  }

  public function setBlockPosition($cursor, $ancestorGeometry, $style, $ancestorStyle) {
    $last = $cursor->skipElement();
    if ($last) {
      $this->formatRow($cursor, $ancestorGeometry, $style, $ancestorStyle);
    }
    $this->x = $style->get('x', $ancestorGeometry->innerWidth);
    if ($this->x < 0) {
      $this->x = $ancestorGeometry->innerWidth - $this->fullWidth + $this->x - $this->marginRight + $ancestorGeometry->paddingRight + $ancestorGeometry->borderRight;
    } else {
      $this->x = $this->x + $this->marginLeft + $ancestorGeometry->paddingLeft + $ancestorGeometry->borderLeft;
    }
    $this->y = $style->get('y', $ancestorGeometry->innerHeight);
    if ($this->y < 0) {
      $this->y = $ancestorGeometry->innerHeight - $this->fullHeight + $this->y - $this->marginBottom + $ancestorGeometry->paddingBottom + $ancestorGeometry->borderBottom;
    } else {
      $this->y = $this->y + $this->marginTop + $ancestorGeometry->paddingTop + $ancestorGeometry->borderTop;
    }
  }

  public function setInlinePosition($cursor, $ancestorGeometry, $style, $ancestorStyle) {
    if (
      $style->get('display') == 'newline' ||
      (
        $ancestorGeometry->width != 'content' &&
        $cursor->x + ($style->get('display') == 'word' && $cursor->lineElements > 0 ? $ancestorStyle->get('wordSpacing') : 0) + $this->width > $ancestorGeometry->innerWidth
      )
    ) {
      $this->formatRow($cursor, $ancestorGeometry, $style, $ancestorStyle);
      $cursor->endLine();
    }
    $last = $cursor->addElement($this->width, $this->height, $this->ascent, $this->descent, $style->get('display') == 'word');
    if ($last) {
      $this->formatRow($cursor, $ancestorGeometry, $style, $ancestorStyle);
      $cursor->endLine();
    }
  }

  protected function formatRow($cursor, $ancestorGeometry, $style, $ancestorStyle) {
    $textAlign = $ancestorStyle->get('textAlign');
    switch ($textAlign) {
      case 'left':
        $this->alignLeft($cursor, $ancestorGeometry, $style);
        break;
      case 'right':
        $this->alignRight($cursor, $ancestorGeometry, $style);
        break;
      case 'justify':
        $this->alignJustify($cursor, $ancestorGeometry, $style);
        break;
      case 'center':
        $this->alignCenter($cursor, $ancestorGeometry, $style);
        break;
    }
  }

  protected function alignLeft($cursor, $ancestorGeometry, $style) {
    $x = $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    for ($i = $cursor->lineFirstElement; $i < $cursor->lineLastElement; $i++) {
      $element = $cursor->elements[$i];
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $estyle->get('display') == 'word';
      if ($isWord && $previousIsWord) {
        $x += $style->get('wordSpacing');
      }
      $geometry->x = $x;
      $fontSize = $style->get('fontSize', $ancestorGeometry->innerHeight);
      $lineHeight = $style->get('lineHeight', $fontSize);
      if ($lineHeight < $fontSize) {
        $lineHeight = $fontSize;
      }
//      $geometry->y = $y + $cursor->y + ($isWord ? ($cursor->lineHeight - $lineHeight + $geometry->baseLine) : 0);
      $geometry->y = $y + $cursor->y + $cursor->ascent - $geometry->ascent;
      $x += $geometry->width;
      $previousIsWord = $isWord;
    }
  }

  protected function alignRight($cursor, $ancestorGeometry, $style) {
    $x = $ancestorGeometry->width - $ancestorGeometry->borderRight - $ancestorGeometry->paddingRight;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    for ($i = $cursor->lineLastElement - 1; $i >= $cursor->lineFirstElement; $i--) {
      $element = $cursor->elements[$i];
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $estyle->get('display') == 'word';
      if ($isWord && $previousIsWord) {
        $x -= $style->get('wordSpacing');
      }
      $x -= $geometry->width;
      $geometry->x = $x;
      $fontSize = $style->get('fontSize', $ancestorGeometry->innerHeight);
      $geometry->y = $y + $cursor->y + ($isWord ? ($cursor->lineHeight - $style->get('lineHeight', $fontSize) + $geometry->baseLine) : 0);
      $previousIsWord = $isWord;
    }
  }

  protected function alignJustify($cursor, $ancestorGeometry, $style) {
    $spaceWidth = 0;
    if ($cursor->s > 0) {
      $spaceWidth = (int)(($ancestorGeometry->innerWidth - $cursor->w) / $cursor->s);
    }
    $x = $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    for ($i = $cursor->lineFirstElement; $i < $cursor->lineLastElement; $i++) {
      $element = $cursor->elements[$i];
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $estyle->get('display') == 'word';
      if ($isWord && $previousIsWord) {
        $x += $spaceWidth;
      }
      $geometry->x = $x;
      $fontSize = $style->get('fontSize', $ancestorGeometry->innerHeight);
      $geometry->y = $y + $cursor->y + ($isWord ? ($cursor->lineHeight - $style->get('lineHeight', $fontSize) + $geometry->baseLine) : 0);
      $x += $geometry->width;
      $previousIsWord = $isWord;
    }
  }

  protected function alignCenter($cursor, $ancestorGeometry, $style) {
    $lw = $cursor->x;
    $x = (int)(($ancestorGeometry->innerWidth - $lw) / 2) + $ancestorGeometry->borderLeft + $ancestorGeometry->paddingLeft;
    $y = $ancestorGeometry->borderTop + $ancestorGeometry->paddingTop;
    $previousIsWord = false;
    for ($i = $cursor->lineFirstElement; $i < $cursor->lineLastElement; $i++) {
      $element = $cursor->elements[$i];
      $geometry = $element->getGeometry();
      $estyle = $element->getStyle();
      $isWord = $estyle->get('display') == 'word';
      if ($isWord && $previousIsWord) {
        $x += $style->get('wordSpacing');
      }
      $geometry->x = $x;
      $fontSize = $style->get('fontSize', $ancestorGeometry->innerHeight);
      $geometry->y = $y + $cursor->y + ($isWord ? ($cursor->lineHeight - $style->get('lineHeight', $fontSize) + $geometry->baseLine) : 0);
      $x += $geometry->width;
      $previousIsWord = $isWord;
    }
  }

}
