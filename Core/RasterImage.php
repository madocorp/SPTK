<?php

namespace SPTK\Core;

use SPTK\Rendering\ImagePixels;

/** Holds an immutable GD-decoded image as packed SDL RGBA pixels. */
final class RasterImage {

  public readonly int $width;
  public readonly int $height;
  public readonly string $pixels;
  public readonly ?string $src;

  /** Decode a path or copy a caller-owned GD image. */
  public function __construct(string|\GdImage $source) {
    $this->src = is_string($source) ? $source : null;
    if ($source instanceof \GdImage) {
      $this->decode($source);
      return;
    }
    if (!extension_loaded('gd')) {
      throw new \RuntimeException('Image requires PHP GD.');
    }
    if (!is_file($source) || !is_readable($source)) {
      throw new \RuntimeException("Cannot read image: {$source}");
    }
    $data = file_get_contents($source);
    $image = $data === false ? false : @imagecreatefromstring($data);
    if ($image === false) {
      foreach (['imagecreatefromxbm', 'imagecreatefromxpm', 'imagecreatefromtga', 'imagecreatefromgd', 'imagecreatefromgd2'] as $loader) {
        if (function_exists($loader)) {
          $image = @$loader($source);
          if ($image !== false) {
            break;
          }
        }
      }
    }
    if ($image === false) {
      throw new \RuntimeException("Unsupported or corrupt image: {$source}");
    }
    try {
      $this->decode($image);
    } finally {
      imagedestroy($image);
    }
  }

  /** Convert GD pixels into native-endian packed SDL RGBA words. */
  private function decode(\GdImage $image): void {
    $this->width = imagesx($image);
    $this->height = imagesy($image);
    if ($this->width < 1 || $this->height < 1) {
      throw new \InvalidArgumentException('Image dimensions must be positive.');
    }
    $this->pixels = ImagePixels::fromGD($image);
  }

}
