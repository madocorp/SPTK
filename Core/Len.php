<?php

namespace SPTK\Core;

/**
 * Describes a layout length used for positions and sizes.
 */
class Len {

  protected function __construct(
    protected string $unit,
    protected int|float|null $value = null
  ) {
  }

  public static function px(int $pixels): self {
    return new self('px', $pixels);
  }

  public static function pixels(int $pixels): self {
    return self::px($pixels);
  }

  public static function cell(int $cells): self {
    return new self('cell', $cells);
  }

  public static function cells(int $cells): self {
    return self::cell($cells);
  }

  public static function percent(float $percent): self {
    return new self('percent', $percent);
  }

  public static function content(): self {
    return new self('content');
  }

  public static function separator(): self {
    return new self('separator');
  }

  public function unit(): string {
    return $this->unit;
  }

  public function value(): int|float|null {
    return $this->value;
  }

  public function resolveCells(int $available, int $cellSize = 1, int $content = 0): int {
    return match ($this->unit) {
      'percent' => (int)floor($available * ((float)$this->value / 100)),
      'content' => max(0, $content),
      'separator' => 1,
      default => max(0, (int)$this->value),
    };
  }

  public function resolvePixels(int $available, int $cellSize, int $content = 0): int {
    return match ($this->unit) {
      'cell' => max(0, (int)$this->value * $cellSize),
      'percent' => (int)floor($available * ((float)$this->value / 100)),
      'content' => max(0, $content * $cellSize),
      'separator' => 2,
      default => max(0, (int)$this->value),
    };
  }

  public function resolveOffsetCells(int $available, int $size, int $cellSize = 1): int {
    $value = match ($this->unit) {
      'percent' => (int)floor($available * ((float)$this->value / 100)),
      default => (int)$this->value,
    };
    return $value < 0 ? $available - $size + $value : $value;
  }

  public function resolveOffsetPixels(int $available, int $size, int $cellSize): int {
    $value = match ($this->unit) {
      'cell' => (int)$this->value * $cellSize,
      'percent' => (int)floor($available * ((float)$this->value / 100)),
      default => (int)$this->value,
    };
    return $value < 0 ? $available - $size + $value : $value;
  }

}
