<?php

namespace SPTK\Rendering;

use SPTK\SDLWrapper\{PNG, SDL};

/** Converts GD pixels through SDL when available, with a portable PHP fallback. */
final class ImagePixels {

  private static ?PNG $png = null;
  private static ?SDL $standaloneSDL = null;
  private static bool $nativeUnavailable = false;

  /** Preserve alpha and palette colors while producing native-endian SDL RGBA words. */
  public static function fromGD(\GdImage $image): string {
    if (!self::nativeAvailable()) {
      return self::fallback($image);
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $data = self::export($image, $width, $height);
    $source = (self::$png ??= new PNG())->pixels($data, $width, $height);
    $sdl = \SPTK\App::sdl() ?? (self::$standaloneSDL ??= new SDL());
    $size = $width * $height * 4;
    $pixels = \FFI::new('char[' . $size . ']');
    $format = pack('L', 1) === "\x01\x00\x00\x00" ? SDL::SDL_PIXELFORMAT_ABGR8888 : SDL::SDL_PIXELFORMAT_RGBA8888;
    $success = $sdl->ffi->SDL_ConvertPixels($width, $height, $format, $source, $width * 4, SDL::SDL_PIXELFORMAT_RGBA8888, $pixels, $width * 4);
    $sdl->checkReturnValue($success, 'SDL_ConvertPixels');
    return \FFI::string($pixels, $size);
  }

  /** Try native dependencies once and remember an unavailable library or API. */
  private static function nativeAvailable(): bool {
    if (self::$nativeUnavailable) {
      return false;
    }
    if (!class_exists('FFI') || !function_exists('imagepng') || !gd_info()['PNG Support']) {
      self::$nativeUnavailable = true;
      return false;
    }
    try {
      self::$png ??= new PNG();
      if (\SPTK\App::sdl() === null) {
        self::$standaloneSDL ??= new SDL();
      }
    } catch (\FFI\Exception $error) {
      self::$nativeUnavailable = true;
      self::$png = null;
      return false;
    }
    return true;
  }

  /** Convert pixels with GD getters when the optional native path cannot load. */
  private static function fallback(\GdImage $image): string {
    $width = imagesx($image);
    $height = imagesy($image);
    $transparent = imagecolortransparent($image);
    $pixels = '';
    for ($y = 0; $y < $height; $y++) {
      $row = [];
      for ($x = 0; $x < $width; $x++) {
        $index = imagecolorat($image, $x, $y);
        $color = imagecolorsforindex($image, $index);
        $alpha = $index === $transparent ? 0 : (int)round((127 - $color['alpha']) * 255 / 127);
        $row[] = ($color['red'] << 24) | ($color['green'] << 16) | ($color['blue'] << 8) | $alpha;
      }
      $pixels .= pack('L*', ...$row);
    }
    return $pixels;
  }

  /** Export an uncompressed PNG snapshot without changing the caller's pixels or save flags. */
  private static function export(\GdImage $image, int $width, int $height): string {
    $copy = imagecreatetruecolor($width, $height);
    if ($copy === false) {
      throw new \RuntimeException('Cannot snapshot GD pixels.');
    }
    imagealphablending($copy, false);
    imagesavealpha($copy, true);
    $transparent = imagecolortransparent($image);
    if ($transparent !== -1) {
      $color = imagecolorsforindex($image, $transparent);
      imagefill($copy, 0, 0, imagecolorallocatealpha($copy, $color['red'], $color['green'], $color['blue'], 127));
    }
    imagecopy($copy, $image, 0, 0, 0, 0, $width, $height);
    ob_start();
    try {
      if (!imagepng($copy, null, 0)) {
        throw new \RuntimeException('Cannot export GD pixels.');
      }
      return ob_get_contents();
    } finally {
      ob_end_clean();
      imagedestroy($copy);
    }
  }

}
