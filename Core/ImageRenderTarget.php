<?php

namespace SPTK\Core;

/**
 * Optional render target extension for bitmap image drawing.
 */
interface ImageRenderTarget extends RenderTarget {

  public function pixelRectForCells(Rect $rect): Rect;

  public function drawImagePixels(\GdImage $image, Rect $sourceRect, Rect $targetRect): void;

}
