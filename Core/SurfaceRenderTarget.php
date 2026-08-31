<?php

namespace SPTK\Core;

/**
 * Optional render target extension for pixel-bounded child grid surfaces.
 */
interface SurfaceRenderTarget extends RenderTarget {

  public function columnsForWidth(int $pixelWidth): int;

  public function rowsForHeight(int $pixelHeight): int;

  public function cellWidth(): int;

  public function cellHeight(): int;

  public function currentSurfacePixelRect(): Rect;

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void;

  public function popSurface(): void;

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void;

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void;

}
