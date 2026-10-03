<?php

namespace SPTK\Core;

/** Shares file-backed images and delays decoding their pixels until requested. */
final class ImageSource {

  private static array $files = [];

  private ?RasterImage $image = null;
  private ?array $dimensions = null;
  private ?string $source;

  /** Reuse a live source for the same file, or snapshot a caller-owned GD image. */
  public static function from(string|\GdImage $source): self {
    if ($source instanceof \GdImage) {
      return new self($source);
    }
    $key = realpath($source) ?: $source;
    $shared = (self::$files[$key] ?? null)?->get();
    if ($shared !== null) {
      return $shared;
    }
    $shared = new self($source);
    self::$files[$key] = \WeakReference::create($shared);
    return $shared;
  }

  /** Retain a file path without reading pixels, or immediately copy GD-owned pixels. */
  private function __construct(string|\GdImage $source) {
    $this->source = is_string($source) ? $source : null;
    if ($source instanceof \GdImage) {
      $this->image = new RasterImage($source);
    }
  }

  /** Read only header dimensions when possible, falling back to GD for unusual formats. */
  public function dimensions(): array {
    if ($this->dimensions !== null) {
      return $this->dimensions;
    }
    if ($this->image === null) {
      $size = @getimagesize($this->source);
      if ($size !== false) {
        return $this->dimensions = [$size[0], $size[1]];
      }
    }
    $image = $this->raster();
    return $this->dimensions = [$image->width, $image->height];
  }

  /** Decode a file once and retain the immutable raster shared by its widgets. */
  public function raster(): RasterImage {
    if ($this->image === null) {
      $this->image = new RasterImage($this->source);
      $this->dimensions = [$this->image->width, $this->image->height];
    }
    return $this->image;
  }

}
