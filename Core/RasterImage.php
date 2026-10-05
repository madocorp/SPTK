<?php

namespace SPTK\Core;

use SPTK\Rendering\ImagePixels;

/** Holds an immutable image as packed SDL RGBA pixels. */
final class RasterImage {

  public readonly int $width;
  public readonly int $height;
  public readonly string $pixels;
  public readonly ?string $src;

  /** Decode a path, copy a GD image, or retain packed RGBA pixels. */
  public function __construct(string|\GdImage|int $source, ?int $height = null, ?string $pixels = null) {
    if (is_int($source)) {
      if ($source < 1 || $height === null || $height < 1 || $pixels === null || strlen($pixels) !== $source * $height * 4) {
        throw new \InvalidArgumentException('Packed raster dimensions or byte count are invalid.');
      }
      $this->width = $source;
      $this->height = $height;
      $this->pixels = $pixels;
      $this->src = null;
      return;
    }
    $this->src = is_string($source) ? $source : null;
    if ($source instanceof \GdImage) {
      $this->decode($source);
      return;
    }
    if (!is_file($source) || !is_readable($source)) {
      throw new \RuntimeException("Cannot read image: {$source}");
    }
    $data = file_get_contents($source);
    if ($data !== false && str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
      try {
        [$this->width, $this->height, $this->pixels] = ImagePixels::fromPNG($data);
      } catch (\RuntimeException|\FFI\Exception $error) {
        throw new \RuntimeException("Cannot decode PNG image: {$source}", 0, $error);
      }
      return;
    }
    if (!extension_loaded('gd')) {
      throw new \RuntimeException('Non-PNG images require PHP GD.');
    }
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
