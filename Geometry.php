<?php

namespace SPTK;

class Geometry {

  public $x = 0;
  public $y = 0;

  public $windowWidth = 0;
  public $windowHeight = 0;
  public $originalWidth = 0;
  public $originalHeight = 0;
  public $width = 0;
  public $height = 0;
  public $innerWidth = 0;
  public $innerHeight = 0;
  public $fullWidth = 0;
  public $fullHeight = 0;
  public $maxWidth = 0;
  public $maxHeight = 0;
  public $minWidth = 0;
  public $minHeight = 0;
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

  public $position;
  public $fontSize = 0;
  public $lineHeight = 0;
  public $ascent = 0;
  public $descent = 0;
  public $wordSpacing = 0;
  public $textAlign = 'left';
  public $textWrap = 'left';

  public $lines = [];

  public function setValues($ancestorGeometry, $style) {
    $this->windowWidth = $ancestorGeometry->windowWidth;
    $this->windowHeight = $ancestorGeometry->windowHeight;
    $this->originalWidth = $this->width;
    $this->originalHeight = $this->height;
    $this->fontSize = $style->get('fontSize');
    $this->textAlign = $style->get('textAlign');
    $this->textWrap = $style->get('textWrap');
    $this->lineHeight = $style->get('lineHeight', $this);
    $this->wordSpacing = $style->get('wordSpacing', $this);
    $this->marginTop = $style->get('marginTop', $ancestorGeometry);
    $this->marginLeft = $style->get('marginLeft', $ancestorGeometry);
    $this->marginBottom = $style->get('marginBottom', $ancestorGeometry);
    $this->marginRight = $style->get('marginRight', $ancestorGeometry);
    $this->borderTop = $style->get('borderTop', $ancestorGeometry);
    $this->borderLeft = $style->get('borderLeft', $ancestorGeometry);
    $this->borderBottom = $style->get('borderBottom', $ancestorGeometry);
    $this->borderRight = $style->get('borderRight', $ancestorGeometry);
    $this->paddingTop = $style->get('paddingTop', $ancestorGeometry);
    $this->paddingLeft = $style->get('paddingLeft', $ancestorGeometry);
    $this->paddingBottom = $style->get('paddingBottom', $ancestorGeometry);
    $this->paddingRight = $style->get('paddingRight', $ancestorGeometry);
    $this->width = $style->get('width', $ancestorGeometry);
    $this->maxWidth = $style->get('maxWidth', $ancestorGeometry);
    $this->maxHeight = $style->get('maxHeight', $ancestorGeometry);
    $this->minWidth = $style->get('minWidth', $ancestorGeometry);
    $this->minHeight = $style->get('minHeight', $ancestorGeometry);
    if ($this->width === 'content') {
      $this->innerWidth = 'content';
      $this->fullWidth = 'content';
    } else {
      if ($this->width < 0) {
        $this->width = $ancestorGeometry->innerWidth + $this->width;
      }
      if ($this->width < $this->minWidth) {
        $this->width = $this->minWidth;
      }
      if ($this->width > $this->maxWidth) {
        $this->width = $this->maxWidth;
      }
      $this->setDerivedWidths();
    }
    $this->height = $style->get('height', $ancestorGeometry);
    if ($this->height === 'content') {
      $this->innerHeight = 'content';
      $this->fullHeight = 'content';
    } else {
      if ($this->height < 0) {
        $this->height = $ancestorGeometry->innerHeight + $this->height;
      }
      if ($this->height < $this->minHeight) {
        $this->height = $this->minHeight;
      }
      if ($this->height > $this->maxHeight) {
        $this->height = $this->maxHeight;
      }
      $this->setDerivedHeights();
    }
    $this->position = $style->get('position');
  }

  public function setDerivedWidths() {
    $this->innerWidth =
      $this->width -
      $this->borderLeft -
      $this->borderRight -
      $this->paddingLeft -
      $this->paddingRight;
    $this->fullWidth =
      $this->width +
      $this->marginLeft +
      $this->marginRight;
  }

  public function setDerivedHeights() {
    $this->innerHeight =
      $this->height -
      $this->borderTop -
      $this->borderBottom -
      $this->paddingTop -
      $this->paddingBottom;
    $this->fullHeight =
      $this->height +
      $this->marginTop +
      $this->marginBottom;
  }

  public function setContentHeight($ascent) {
    $this->contentHeight = 0;
    foreach ($this->lines as $line) {
      $this->contentHeight += $line['ascent'] + $line['descent'];
    }
    if ($this->height === 'content') {
      $this->height =
        $this->borderTop +
        $this->paddingTop +
        $this->contentHeight +
        $this->paddingBottom +
        $this->borderBottom;
      $this->setDerivedHeights();
    }
    if ($ascent == 'auto') {
      if (isset($this->lines[0])) {
        $this->ascent = $this->lines[0]['ascent'] + $this->paddingTop + $this->borderTop + $this->marginTop;
        $this->descent = $this->fullHeight - $this->ascent;
      } else {
        $this->ascent = $this->fullHeight - $this->marginBottom;
        $this->descent = $this->marginBottom;
      }
    } else if ($ascent == 'content') {
      $this->ascent = $this->fullHeight - $this->marginBottom;
      $this->descent = $this->marginBottom;
    } else {
      $this->ascent = $ascent + $this->marginTop;
      $this->descent = $this->fullHeight - $this->ascent;
    }
  }

  public function setAbsolutePosition($ancestorGeometry, $style) {
    $this->x = $style->get('x', $ancestorGeometry, $isNegative);
    if ($this->x === 'middle') {
      $this->x = (int)(($ancestorGeometry->width - $this->fullWidth) / 2);
    } else if ($isNegative) {
      $this->x = $ancestorGeometry->width - $ancestorGeometry->paddingRight - $ancestorGeometry->borderRight - $this->fullWidth + $this->x - $this->marginRight;
    } else {
      $this->x = $this->x + $this->marginLeft + $ancestorGeometry->paddingLeft + $ancestorGeometry->borderLeft;
    }
    $this->y = $style->get('y', $ancestorGeometry, $isNegative);
    if ($this->y === 'middle') {
      $this->y = (int)(($ancestorGeometry->height - $this->fullHeight) / 2);
    } else if ($isNegative) {
      $this->y = $ancestorGeometry->height - $ancestorGeometry->paddingBottom - $ancestorGeometry->borderBottom - $this->fullHeight + $this->y - $this->marginBottom;
    } else {
      $this->y = $this->y + $this->marginTop + $ancestorGeometry->paddingTop + $ancestorGeometry->borderTop;
    }
  }

  public function sizeChanged() {
    return ($this->originalWidth !== $this->width || $this->originalHeight !== $this->height);
  }

}
