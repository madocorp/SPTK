<?php

namespace SPTK;

class Image extends Element {

  public $value = false;
  protected $surface;
  protected $width;
  protected $height;

  private static $bgColor = false;

  public function getAttributeList() {
    return ['value'];
  }

  public function setValue($value) {
    if (strpos($value, '/') !== 0) {
      if (defined('APP_PATH')) {
        $dir = dirname(APP_PATH);
        $this->value = "{$dir}/{$value}";
      } else {
        $this->value = getcwd() . '/' . $value;
      }
    }
    if (file_exists($this->value)) {
      $this->load();
    }
  }

  protected function measure() {
    $this->geometry->width = $this->width;
    $this->geometry->height = $this->height;
    $this->geometry->ascent = $this->height;
    $this->geometry->descent = 0;
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
  }

  protected function calculateWidths() {
    ;
  }

  protected function calculateHeights() {
    ;
  }

  protected function layout() {
    ;
  }

  protected function draw() {
    // lazy load?
  }

  protected function render() {
    return $this->texture;
  }

  protected function load() {
    $img = imagecreatefrompng($this->value);
    if (!$img) {
      throw new \Exception("Failed to load image: {$this->value}");
    }
    imagepalettetotruecolor($img);
    imagesavealpha($img, true);
    $this->width  = imagesx($img);
    $this->height = imagesy($img);
    $sdl = SDL::$instance->sdl;
    $surface = $sdl->cast("SDL_Surface *", $this->surface);
    if (self::$bgColor == false) {
      self::$bgColor = $sdl->new("SDL_Color");
    }
    $bgcolor = $this->style->get('backgroundColor');
    self::$bgColor->r = $bgcolor[0];
    self::$bgColor->g = $bgcolor[1];
    self::$bgColor->b = $bgcolor[2];
    self::$bgColor->a = $bgcolor[3] ?? 0xff;
    // convert to RGBA (little-endian)
    $size = $this->width * $this->height * 4;
    $rgba = \FFI::new("uint8_t[{$size}]");
    $offset = 0;
    for ($y = 0; $y < $this->height; $y++) {
      for ($x = 0; $x < $this->width; $x++) {
        $c = imagecolorat($img, $x, $y);
        $a = 255 - intdiv((($c >> 24) & 0x7F) * 255, 127);
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;
        $rgba[$offset++] = $a;
        $rgba[$offset++] = $b;
        $rgba[$offset++] = $g;
        $rgba[$offset++] = $r;
      }
    }
    // copy RGBA data to a new surface
    $surface = $sdl->SDL_CreateSurface($this->width, $this->height, SDL::SDL_PIXELFORMAT_RGBA8888);
    $sdl->SDL_LockSurface($surface);
    $pixels = $surface->pixels; // void* pointer
    $pitch  = $surface->pitch;  // int
    $srcStride = $this->width * 4;
    $srcOffset = 0;
    for ($y = 0; $y < $this->height; $y++) {
      $dst = \FFI::cast("uint8_t*", $surface->pixels) + $y * $surface->pitch;
      \FFI::memcpy($dst, \FFI::addr($rgba[$srcOffset]), $srcStride);
      $srcOffset += $srcStride;
    }
    $sdl->SDL_UnlockSurface($surface);
    // create a Texture from the surface
    $this->texture = new Texture($this->renderer, $this->width, $this->height, $bgcolor, $surface);
  }

}
