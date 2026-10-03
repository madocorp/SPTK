<?php

namespace SPTK\SDLWrapper;

/** Decodes in-memory PNG data into native RGBA bytes through libpng's public simplified API. */
final class PNG {

  private const FORMAT_RGBA = 3;

  private \FFI $ffi;

  /** Load the public libpng image API used for bulk GD pixel transfer. */
  public function __construct() {
    $this->ffi = \FFI::cdef(
      file_get_contents(APP_DIR . '/SPTK/SDLWrapper/png_extract.h'),
      'libpng16.so.16',
    );
  }

  /** Decode all pixels in native code and release libpng's temporary allocations. */
  public function pixels(string $data, int $width, int $height): \FFI\CData {
    $image = $this->ffi->new('png_image');
    $image->version = 1;
    try {
      if (!$this->ffi->png_image_begin_read_from_memory(\FFI::addr($image), $data, strlen($data))) {
        throw new \RuntimeException('Cannot read PNG pixels: ' . \FFI::string($image->message));
      }
      if ($image->width !== $width || $image->height !== $height) {
        throw new \RuntimeException('PNG pixel dimensions do not match the GD source.');
      }
      $image->format = self::FORMAT_RGBA;
      $pixels = \FFI::new('char[' . ($width * $height * 4) . ']');
      if (!$this->ffi->png_image_finish_read(\FFI::addr($image), null, $pixels, 0, null)) {
        throw new \RuntimeException('Cannot decode PNG pixels: ' . \FFI::string($image->message));
      }
      return $pixels;
    } finally {
      $this->ffi->png_image_free(\FFI::addr($image));
    }
  }

}
