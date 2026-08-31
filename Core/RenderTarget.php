<?php

namespace SPTK\Core;

/**
 * Receives ordered drawing commands emitted by elements during rendering.
 */
interface RenderTarget {

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void;

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void;

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void;

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void;

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void;

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void;

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void;

  public function pushClip(Rect $rect): void;

  public function popClip(): void;

}
