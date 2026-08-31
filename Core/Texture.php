<?php

namespace SPTK\Core;

/**
 * Pixel drawing surface for custom graphics and texture composition.
 */
interface Texture {

  public function width(): int;

  public function height(): int;

  public function clear(Color|string|int $color = 'transparent'): void;

  public function drawLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void;

  public function drawRect(int $x, int $y, int $width, int $height, Color|string|int $color, int $thickness = 1): void;

  public function fillRect(int $x, int $y, int $width, int $height, Color|string|int $color): void;

  public function copyTo(Texture $target, int $x, int $y): void;

  public function copy(Texture $target, int $sourceX, int $sourceY, int $targetX, int $targetY, int $width, int $height): void;

}
