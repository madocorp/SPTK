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
    if ($vb !== false) {
      $this->texture->drawFillRect($vb[0], $vb[2], $vb[1], $vb[3], $barColor);
    }
    if ($hb !== false) {
      $this->texture->drawFillRect($hb[0], $hb[2], $hb[1], $hb[3], $barColor);
    }
    if ($vb !== false) {
      $this->texture->drawFillRect($vb[0], $vb[4], $vb[1], $vb[5], $handleColor);
    }
    if ($hb !== false) {
      $this->texture->drawFillRect($hb[4], $hb[2], $hb[5], $hb[3], $handleColor);
    }
  }

  private function vertical($geometry, $sy, $my, $size) {
    $y1 = $geometry->borderTop;
    $y2 = $geometry->height - $geometry->borderBottom;
    $barHeight = $y2 - $y1;
    if ($my <= $barHeight + 1) {
      return false;
    }
    $x1 = $geometry->width - $geometry->borderRight - $size;
    $x2 = $geometry->width - $geometry->borderRight;
    $handlePos = (int)($barHeight * $sy / $my) + $geometry->borderTop;
    $handleHeight = (int)($barHeight * $barHeight / $my);
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleHeight];
  }

  private function horizontal($geometry, $sx, $mx, $size, $hasVertical) {
    $x1 = $geometry->borderLeft;
    $x2 = $geometry->width - $geometry->borderRight - ($hasVertical ? $size : 0);
    $barWidth = $x2 - $x1;
    if ($mx - ($hasVertical ? $size : 0) <= $barWidth + 1) {
      return false;
    }
    $y1 = $geometry->height - $geometry->borderBottom - $size;
    $y2 = $geometry->height - $geometry->borderBottom;
    $handlePos = (int)($barWidth * $sx / $mx) + $geometry->borderLeft;
    $handleWidth = (int)($barWidth * $barWidth / $mx);
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleWidth];
  }

}
