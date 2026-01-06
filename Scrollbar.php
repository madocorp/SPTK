<?php

namespace SPTK;

class Scrollbar {

  protected $texture;

  public function __construct($texture, $sx, $sy, $mx, $my, $geometry, $style) {
    $this->texture = $texture;
    $barColor = $style->get('scrollbarColor');
    $handleColor = $style->get('scrollhandleColor');
    $size = $style->get('scrollbarSize');
    $vb = $this->vertical($geometry, $sy, $my, $size);
    $hb = $this->horizontal($geometry, $sx, $mx, $size, $vb !== false);
    if ($vb !== false && $barColor !== 'transparent') {
      $this->texture->drawFillRect($vb[0], $vb[2], $vb[1], $vb[4], $barColor);
      $this->texture->drawFillRect($vb[0], $vb[5], $vb[1], $vb[3], $barColor);
    }
    if ($hb !== false && $barColor !== 'transparent') {
      $this->texture->drawFillRect($hb[0], $hb[2], $hb[4], $hb[3], $barColor);
      $this->texture->drawFillRect($hb[5], $hb[2], $hb[1], $hb[3], $barColor);
    }
    if ($vb !== false && $handleColor !== 'transparent') {
      $this->texture->drawFillRect($vb[0], $vb[4], $vb[1], $vb[5], $handleColor);
    }
    if ($hb !== false && $handleColor !== 'transparent') {
      $this->texture->drawFillRect($hb[4], $hb[2], $hb[5], $hb[3], $handleColor);
    }
  }

  private function vertical($geometry, $sy, $my, $size) {
    $y1 = $geometry->borderTop;
    $y2 = $geometry->height - $geometry->borderBottom;
    $barHeight = $y2 - $y1;
    if ($my - $geometry->borderTop <= $barHeight + 1) {
      return false;
    }
    $x1 = $geometry->width - $geometry->borderRight - $size;
    $x2 = $geometry->width - $geometry->borderRight;
    $handlePos = round($barHeight * $sy / $my) + $geometry->borderTop;
    $handleHeight = round($barHeight * $geometry->innerHeight / $my);
    if ($handlePos + $handleHeight > $y2) {
      $handleHeight = $y2 - $handlePos;
    }
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleHeight];
  }

  private function horizontal($geometry, $sx, $mx, $size, $hasVertical) {
    $x1 = $geometry->borderLeft;
    $x2 = $geometry->width - $geometry->borderRight - ($hasVertical ? $size : 0);
    $barWidth = $x2 - $x1;
    if ($mx <= 0) {
      return false;
    }
    if ($mx - $geometry->borderLeft - ($hasVertical ? $size : 0) <= $barWidth + 1) {
      return false;
    }
    $y1 = $geometry->height - $geometry->borderBottom - $size;
    $y2 = $geometry->height - $geometry->borderBottom;
    $handlePos = round($barWidth * $sx / $mx) + $geometry->borderLeft;
    $handleWidth = round($barWidth * $geometry->innerWidth / $mx);
    if ($handlePos + $handleWidth > $x2) {
      $handleWidth = $x2 - $handlePos;
    }
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleWidth];
  }

}
