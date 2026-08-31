<?php

namespace SPTK\Core;

/**
 * Optional render target extension for pixel-positioned text.
 */
interface PixelTextRenderTarget extends RenderTarget {

  public function measureTextPixels(string $text, array $font = []): array;

  public function fontMetricsPixels(array $font = []): array;

  public function drawTextPixels(string $text, Rect $targetRect, Color|string|int $color, array $font = []): void;

}
