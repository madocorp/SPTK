<?php

namespace SPTK\Core;

/**
 * Describes how a child element is positioned by a parent layout.
 */
class Place {

  protected function __construct(
    protected string $mode,
    protected array $options = []
  ) {
  }

  public static function dock(string $edge, ?Len $size = null, ?Len $separator = null): self {
    if (!in_array($edge, ['top', 'right', 'bottom', 'left'], true)) {
      throw new \InvalidArgumentException("Invalid dock edge: {$edge}");
    }
    return new self('dock', [
      'edge' => $edge,
      'size' => $size,
      'separator' => $separator,
    ]);
  }

  public static function fill(): self {
    return new self('fill');
  }

  public static function at(Len $x, Len $y, ?Len $width = null, ?Len $height = null): self {
    return new self('at', [
      'x' => $x,
      'y' => $y,
      'width' => $width,
      'height' => $height,
    ]);
  }

  public static function cursor(?Len $height = null, ?Len $width = null): self {
    return new self('cursor', [
      'height' => $height,
      'width' => $width,
    ]);
  }

  public function mode(): string {
    return $this->mode;
  }

  public function edge(): ?string {
    return $this->options['edge'] ?? null;
  }

  public function size(): ?Len {
    return $this->options['size'] ?? null;
  }

  public function separator(): ?Len {
    return $this->options['separator'] ?? null;
  }

  public function x(): ?Len {
    return $this->options['x'] ?? null;
  }

  public function y(): ?Len {
    return $this->options['y'] ?? null;
  }

  public function width(): ?Len {
    return $this->options['width'] ?? null;
  }

  public function height(): ?Len {
    return $this->options['height'] ?? null;
  }

}
