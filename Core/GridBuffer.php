<?php

namespace SPTK\Core;

/**
 * Stores a rectangular character-cell surface and records changed regions.
 */
class GridBuffer implements RenderTarget {

  protected array $cells = [];
  protected array $dirty = [];
  protected array $clips = [];

  public function __construct(
    protected int $width,
    protected int $height,
    protected Cell $blank = new Cell()
  ) {
    for ($y = 0; $y < $height; $y++) {
      $this->cells[$y] = [];
      for ($x = 0; $x < $width; $x++) {
        $this->cells[$y][$x] = $blank->copy();
      }
    }
    $this->markDirty(new Rect(0, 0, $width, $height));
  }

  public function width(): int {
    return $this->width;
  }

  public function height(): int {
    return $this->height;
  }

  public function rect(): Rect {
    return new Rect(0, 0, $this->width, $this->height);
  }

  public function clear(?Cell $cell = null): void {
    $cell ??= $this->blank;
    for ($y = 0; $y < $this->height; $y++) {
      for ($x = 0; $x < $this->width; $x++) {
        $this->cells[$y][$x] = $cell->copy();
      }
    }
    $this->markDirty($this->rect());
  }

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
      return;
    }
    if (!$this->pointVisible($x, $y)) {
      return;
    }
    $cell = $value instanceof Cell ? $value->copy() : new Cell($value, $fg ?? '#ffffff', $bg ?? '#000000', $flags);
    $this->cells[$y][$x] = $cell;
    $this->markDirty(new Rect($x, $y, 1, 1));
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
      $chars = str_split($text);
    }
    foreach ($chars as $i => $char) {
      $this->put($x + $i, $y, $char, $fg, $bg, $flags);
    }
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $clip = $rect->intersect($this->clipRect());
    if ($clip === null) {
      return;
    }
    $cell = $value instanceof Cell ? $value : new Cell($value, $fg ?? '#ffffff', $bg ?? '#000000', $flags);
    for ($y = $clip->y; $y < $clip->bottom(); $y++) {
      for ($x = $clip->x; $x < $clip->right(); $x++) {
        $this->cells[$y][$x] = $cell->copy();
      }
    }
    $this->markDirty($clip);
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fill($rect, $value, $fg, $bg, $flags);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
    if ((int)$y1 === (int)$y2) {
      $y = (int)$y1;
      for ($x = (int)floor(min($x1, $x2)); $x < (int)ceil(max($x1, $x2)); $x++) {
        $this->put($x, $y, '-', $color);
      }
      return;
    }
    if ((int)$x1 === (int)$x2) {
      $x = (int)$x1;
      for ($y = (int)floor(min($y1, $y2)); $y < (int)ceil(max($y1, $y2)); $y++) {
        $this->put($x, $y, '|', $color);
      }
    }
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    if ($rect->width < 2 || $rect->height < 2) {
      return;
    }
    $x1 = $rect->x;
    $y1 = $rect->y;
    $x2 = $rect->right() - 1;
    $y2 = $rect->bottom() - 1;
    for ($x = $x1 + 1; $x < $x2; $x++) {
      $this->put($x, $y1, '-', $color);
      $this->put($x, $y2, '-', $color);
    }
    for ($y = $y1 + 1; $y < $y2; $y++) {
      $this->put($x1, $y, '|', $color);
      $this->put($x2, $y, '|', $color);
    }
    $this->put($x1, $y1, '+', $color);
    $this->put($x2, $y1, '+', $color);
    $this->put($x1, $y2, '+', $color);
    $this->put($x2, $y2, '+', $color);
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    $this->drawRect($rect, $color, $thickness);
  }

  public function pushClip(Rect $rect): void {
    $clip = $rect->intersect($this->clipRect());
    $this->clips[] = $clip ?? new Rect(0, 0, 0, 0);
  }

  public function popClip(): void {
    array_pop($this->clips);
  }

  public function blit(GridBuffer $source, int $targetX = 0, int $targetY = 0, ?Rect $sourceRect = null): void {
    $sourceRect ??= $source->rect();
    for ($y = 0; $y < $sourceRect->height; $y++) {
      for ($x = 0; $x < $sourceRect->width; $x++) {
        $cell = $source->cell($sourceRect->x + $x, $sourceRect->y + $y);
        if ($cell !== null) {
          $this->put($targetX + $x, $targetY + $y, $cell);
        }
      }
    }
  }

  public function cell(int $x, int $y): ?Cell {
    if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
      return null;
    }
    return $this->cells[$y][$x]->copy();
  }

  public function line(int $y): string {
    if ($y < 0 || $y >= $this->height) {
      return '';
    }
    $line = '';
    for ($x = 0; $x < $this->width; $x++) {
      $line .= $this->cells[$y][$x]->glyph;
    }
    return $line;
  }

  public function lines(): array {
    $lines = [];
    for ($y = 0; $y < $this->height; $y++) {
      $lines[] = $this->line($y);
    }
    return $lines;
  }

  public function dirtyRegions(): array {
    return $this->dirty;
  }

  public function clearDirty(): void {
    $this->dirty = [];
  }

  protected function markDirty(Rect $rect): void {
    if ($rect->width > 0 && $rect->height > 0) {
      $this->dirty[] = $rect;
    }
  }

  protected function clipRect(): Rect {
    return $this->clips[count($this->clips) - 1] ?? $this->rect();
  }

  protected function pointVisible(int $x, int $y): bool {
    $clip = $this->clipRect();
    return $x >= $clip->x && $y >= $clip->y && $x < $clip->right() && $y < $clip->bottom();
  }

}
