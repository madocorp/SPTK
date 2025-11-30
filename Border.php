<?php

namespace SPTK;

class Border {

  protected $texture;
  protected $w;
  protected $h;

  public function __construct($texture, $geometry, $ancestorGeometry, $style) {
    $this->texture = $texture;
    $left = $style->get('borderLeft', $ancestorGeometry->innerWidth);
    $right = $style->get('borderRight', $ancestorGeometry->innerWidth);
    $top = $style->get('borderTop', $ancestorGeometry->innerHeight);
    $bottom = $style->get('borderBottom', $ancestorGeometry->innerHeight);
    $paddingLeft = $style->get('paddingLeft', $ancestorGeometry->innerWidth);
    $paddingRight = $style->get('paddingRight', $ancestorGeometry->innerWidth);
    $paddingTop = $style->get('paddingTop', $ancestorGeometry->innerHeight);
    $paddingBottom = $style->get('paddingBottom', $ancestorGeometry->innerHeight);
    $this->w = $geometry->innerWidth + $left + $right + $paddingLeft + $paddingRight - 1;
    $this->h = $geometry->innerHeight + $top + $bottom + $paddingTop + $paddingBottom - 1;
    if ($left > 0) {
      $color = $style->get('borderColorLeft');
      $this->borderLeft($left, $top, $bottom, $color);
    }
    $right = $style->get('borderRight', $ancestorGeometry->innerWidth);
    if ($right > 0) {
      $color = $style->get('borderColorRight');
      $this->borderRight($right, $top, $bottom, $color);
    }
    $top = $style->get('borderTop', $ancestorGeometry->innerHeight);
    if ($top > 0) {
      $color = $style->get('borderColorTop');
      $this->borderTop($top, $left, $right, $color);
    }
    $bottom = $style->get('borderBottom', $ancestorGeometry->innerHeight);
    if ($bottom > 0) {
      $color = $style->get('borderColorBottom');
      $this->borderBottom($bottom, $left, $right, $color);
    }
  }

  protected function borderLeft($left, $top, $bottom, $color) {
    $ts = $top / $left;
    $bs = $bottom / $left;
    for ($i = $ti = $bi = 0; $i < $left; $i++, $ti += $ts, $bi += $bs) {
      $this->texture->drawLine($i, (int)$ti, $i, $this->h - (int)$bi, $color);
    }
  }

  protected function borderRight($right, $top, $bottom, $color) {
    $ts = $top / $right;
    $bs = $bottom / $right;
    for ($i = $ti = $bi = 0; $i < $right; $i++, $ti += $ts, $bi += $bs) {
      $this->texture->drawLine($this->w - $i, (int)$ti, $this->w - $i, $this->h - (int)$bi, $color);
    }
  }

  protected function borderTop($top, $left, $right, $color) {
    $ls = $left / $top;
    $rs = $right / $top;
    for ($i = $li = $ri = 0; $i < $top; $i++, $li += $ls, $ri += $rs) {
      $this->texture->drawLine((int)$li, $i, $this->w - (int)$ri, $i, $color);
    }
  }

  protected function borderBottom($bottom, $left, $right, $color) {
    $ls = $left / $bottom;
    $rs = $right / $bottom;
    for ($i = $li = $ri = 0; $i < $bottom; $i++, $li += $ls, $ri += $rs) {
      $this->texture->drawLine((int)$li, $this->h - $i, $this->w - (int)$ri, $this->h - $i, $color);
    }
  }

}
