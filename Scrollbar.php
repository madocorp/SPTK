<?php

namespace SPTK;

class Scrollbar {

  protected $texture;

  public function __construct(Texture $texture, int $sx, int $sy, int $mx, int $my, Geometry $geometry, Style $style, array $options = []) {
    $this->texture = $texture;
    $barColor = $style->get('scrollbarColor');
    $handleColor = $style->get('scrollhandleColor');
    $size = $style->get('scrollbarSize');
    $vb = $this->vertical($geometry, $sy, $my, $size, $options);
    $hb = $this->horizontal($geometry, $sx, $mx, $size, $vb !== false);
    if ($vb !== false && $barColor !== 'transparent') {
      $this->drawFillTriangle($vb[0], $vb[2], $vb[1], $vb[2], $vb[1], $vb[4], $barColor);
      $this->drawFillTriangle($vb[0], $vb[3], $vb[1], $vb[3], $vb[1], $vb[5], $barColor);
    }
    if ($hb !== false && $barColor !== 'transparent') {
      $this->drawFillTriangle($hb[0], $hb[2], $hb[0], $hb[3], $hb[4], $hb[3], $barColor);
      $this->drawFillTriangle($hb[1], $hb[2], $hb[1], $hb[3], $hb[5], $hb[3], $barColor);
    }
    if ($vb !== false && $handleColor !== 'transparent') {
      $this->texture->drawFillRect($vb[0], $vb[4], $vb[1], $vb[5], $handleColor);
    }
    if ($hb !== false && $handleColor !== 'transparent') {
      $this->texture->drawFillRect($hb[4], $hb[2], $hb[5], $hb[3], $handleColor);
    }
  }

  private function vertical(Geometry $geometry, int $sy, int $my, int $size, array $options) {
    $y1 = $options['verticalTop'] ?? $geometry->borderTop;
    $y2 = $options['verticalBottom'] ?? ($geometry->height - $geometry->borderBottom);
    $barHeight = $y2 - $y1;
    $hasViewportHeight = array_key_exists('verticalViewportHeight', $options);
    $viewportHeight = $hasViewportHeight ? $options['verticalViewportHeight'] : $geometry->innerHeight;
    if (($hasViewportHeight ? $my <= $viewportHeight + 1 : $my - $geometry->borderTop <= $barHeight + 1) || $barHeight <= 0) {
      return false;
    }
    $x1 = $geometry->width - $geometry->borderRight - $size;
    $x2 = $geometry->width - $geometry->borderRight;
    $handlePos = round($barHeight * $sy / $my) + $y1;
    $handleHeight = round($barHeight * $viewportHeight / $my);
    if ($handlePos + $handleHeight > $y2) {
      $handleHeight = $y2 - $handlePos;
    }
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleHeight];
  }

  private function horizontal(Geometry $geometry, int $sx, int $mx, int $size, bool $hasVertical) {
    $x1 = $geometry->borderLeft;
    $x2 = $geometry->width - $geometry->borderRight - ($hasVertical ? $size : 0);
    $barWidth = $x2 - $x1;
    if ($mx <= 0) {
      return false;
    }
    if ($mx <= $geometry->innerWidth + 1) {
      return false;
    }
    $y1 = $geometry->height - $geometry->borderBottom - $size;
    $y2 = $geometry->height - $geometry->borderBottom;
    $handlePos = round($barWidth * $sx / $mx) + $x1;
    $handleWidth = round($barWidth * $geometry->innerWidth / $mx);
    if ($handlePos + $handleWidth > $x2) {
      $handleWidth = $x2 - $handlePos;
    }
    return [$x1, $x2, $y1, $y2, $handlePos, $handlePos + $handleWidth];
  }

  private function drawFillTriangle(int $x1, int $y1, int $x2, int $y2, int $x3, int $y3, array $color): void {
    $minY = min($y1, $y2, $y3);
    $maxY = max($y1, $y2, $y3);
    if ($minY === $maxY) {
      return;
    }

    for ($y = $minY; $y < $maxY; $y++) {
      $lineY = $y + 0.5;
      $intersections = [];
      $this->addTriangleIntersection($intersections, $x1, $y1, $x2, $y2, $lineY);
      $this->addTriangleIntersection($intersections, $x2, $y2, $x3, $y3, $lineY);
      $this->addTriangleIntersection($intersections, $x3, $y3, $x1, $y1, $lineY);
      if (count($intersections) < 2) {
        continue;
      }
      sort($intersections);
      $startX = (int)floor($intersections[0]);
      $endX = (int)ceil($intersections[count($intersections) - 1]);
      if ($startX < $endX) {
        $this->texture->drawFillRect($startX, $y, $endX, $y + 1, $color);
      }
    }
  }

  private function addTriangleIntersection(array &$intersections, int $x1, int $y1, int $x2, int $y2, float $lineY): void {
    if ($y1 === $y2) {
      return;
    }
    $minY = min($y1, $y2);
    $maxY = max($y1, $y2);
    if ($lineY < $minY || $lineY >= $maxY) {
      return;
    }
    $intersections[] = $x1 + ($lineY - $y1) * ($x2 - $x1) / ($y2 - $y1);
  }

}
