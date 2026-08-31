<?php

namespace SPTK\Core;

/**
 * Draws SPTK-style proportional scrollbar decorations for a scrollable range.
 */
class Scrollbar extends Element {

  public function __construct(
    string $name = '',
    protected int $total = 1,
    protected int $visible = 1,
    protected int $offset = 0,
    protected string $orientation = 'vertical'
  ) {
    parent::__construct($name);
  }

  protected function paint(RenderTarget $target): void {
    self::paintBar($target, $this->frame, $this->offset, $this->visible, $this->total, $this->orientation, $this->theme->markerFg);
  }

  public static function paintBar(
    RenderTarget $target,
    Rect $rect,
    int $offset,
    int $visible,
    int $total,
    string $orientation,
    string|int|Color $color
  ): void {
    $length = $orientation === 'vertical' ? $rect->height : $rect->width;
    if ($length <= 0 || $visible <= 0 || $total <= $visible) {
      return;
    }
    $offset = max(0, min($offset, $total - $visible));
    if (method_exists($target, 'drawScrollbar')) {
      $target->drawScrollbar($rect, $orientation, $offset, $visible, $total, $color);
      return;
    }
    $handleStart = (int)round($length * $offset / $total);
    $handleSize = max(1, (int)round($length * $visible / $total));
    if ($handleStart + $handleSize > $length) {
      $handleSize = $length - $handleStart;
    }
    $handleEnd = $handleStart + $handleSize;
    if ($orientation === 'vertical') {
      self::paintVertical($target, $rect, $handleStart, $handleEnd, $color);
    } else {
      self::paintHorizontal($target, $rect, $handleStart, $handleEnd, $color);
    }
  }

  protected static function paintVertical(RenderTarget $target, Rect $rect, int $handleStart, int $handleEnd, string|int|Color $color): void {
    self::paintVerticalGrid($target, $rect, $handleStart, $handleEnd, $color);
  }

  protected static function paintHorizontal(RenderTarget $target, Rect $rect, int $handleStart, int $handleEnd, string|int|Color $color): void {
    $height = method_exists($target, 'scrollbarThickness')
      ? (float)$target->scrollbarThickness('horizontal')
      : 1.0;
    $y1 = max($rect->y, $rect->bottom() - $height);
    $y2 = $rect->bottom();
    if ($handleStart > 0) {
      self::drawFillTriangle($target, $rect->x, $y1, $rect->x, $y2, $rect->x + $handleStart, $y2, $color);
    }
    if ($handleEnd < $rect->width) {
      self::drawFillTriangle($target, $rect->right(), $y1, $rect->right(), $y2, $rect->x + $handleEnd, $y2, $color);
    }
  }

  protected static function paintVerticalGrid(RenderTarget $target, Rect $rect, int $handleStart, int $handleEnd, string|int|Color $color): void {
    if ($handleStart > 0) {
      self::drawFillTriangle($target, $rect->x, $rect->y, $rect->right(), $rect->y, $rect->right(), $rect->y + $handleStart, $color);
    }
    if ($handleEnd < $rect->height) {
      self::drawFillTriangle($target, $rect->x, $rect->bottom(), $rect->right(), $rect->bottom(), $rect->right(), $rect->y + $handleEnd, $color);
    }
  }

  protected static function drawFillTriangle(RenderTarget $target, float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, string|int|Color $color): void {
    if (method_exists($target, 'drawFillTriangle')) {
      $target->drawFillTriangle($x1, $y1, $x2, $y2, $x3, $y3, $color);
    }
  }

}
