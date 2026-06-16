<?php

namespace SPTK;

class Border {

  protected $texture;
  protected $w;
  protected $h;

  public function __construct(Texture $texture, Geometry $geometry, Geometry $ancestorGeometry, Style $style) {
    $this->texture = $texture;
    $left = $style->get('borderLeft', $ancestorGeometry);
    $right = $style->get('borderRight', $ancestorGeometry);
    $top = $style->get('borderTop', $ancestorGeometry);
    $bottom = $style->get('borderBottom', $ancestorGeometry);
    if ($left + $right + $top + $bottom === 0) {
      return;
    }
    $paddingLeft = $style->get('paddingLeft', $ancestorGeometry);
    $paddingRight = $style->get('paddingRight', $ancestorGeometry);
    $paddingTop = $style->get('paddingTop', $ancestorGeometry);
    $paddingBottom = $style->get('paddingBottom', $ancestorGeometry);
    $this->w = $geometry->innerWidth + $left + $right + $paddingLeft + $paddingRight - 1;
    $this->h = $geometry->innerHeight + $top + $bottom + $paddingTop + $paddingBottom - 1;
    if ($left > 0) {
      $color = $style->get('borderColorLeft');
      $this->borderLeft($left, $top, $bottom, $color);
    }
    $right = $style->get('borderRight', $ancestorGeometry);
    if ($right > 0) {
      $color = $style->get('borderColorRight');
      $this->borderRight($right, $top, $bottom, $color);
    }
    $top = $style->get('borderTop', $ancestorGeometry);
    if ($top > 0) {
      $color = $style->get('borderColorTop');
      $this->borderTop($top, $left, $right, $color);
    }
    $bottom = $style->get('borderBottom', $ancestorGeometry);
    if ($bottom > 0) {
      $color = $style->get('borderColorBottom');
      $this->borderBottom($bottom, $left, $right, $color);
    }
  }

  protected function borderLeft(int $left, int $top, int $bottom, array $color): void {
    $ts = $top / $left;
    $bs = $bottom / $left;
    for ($i = $ti = $bi = 0; $i < $left; $i++, $ti += $ts, $bi += $bs) {
      $this->texture->drawLine($i, (int)$ti, $i, $this->h - (int)$bi, $color);
    }
  }

  protected function borderRight(int $right, int $top, int $bottom, array $color): void {
    $ts = $top / $right;
    $bs = $bottom / $right;
    for ($i = $ti = $bi = 0; $i < $right; $i++, $ti += $ts, $bi += $bs) {
      $this->texture->drawLine($this->w - $i, (int)$ti, $this->w - $i, $this->h - (int)$bi, $color);
    }
  }

  protected function borderTop(int $top, int $left, int $right, array $color): void {
    $ls = $left / $top;
    $rs = $right / $top;
    for ($i = $li = $ri = 0; $i < $top; $i++, $li += $ls, $ri += $rs) {
      $this->texture->drawLine((int)$li, $i, $this->w - (int)$ri, $i, $color);
    }
  }

  protected function borderBottom(int $bottom, int $left, int $right, array $color): void {
    $ls = $left / $bottom;
    $rs = $right / $bottom;
    for ($i = $li = $ri = 0; $i < $bottom; $i++, $li += $ls, $ri += $rs) {
      $this->texture->drawLine((int)$li, $this->h - $i, $this->w - (int)$ri, $this->h - $i, $color);
    }
  }

}
