<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Texture;
use \SPTK\SDLWrapper\SDL;

class Image extends Element {

  public $value = false;
  protected $img;
  protected $width;
  protected $height;

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

  protected function calculateWidths() {
    if ($this->geometry->width < 0) {
      $this->geometry->width = $this->ancestor->geometry->innerWidth + $this->geometry->width;
    }
    if ($this->geometry->width === 'content') {
      $this->geometry->width =
        $this->geometry->borderLeft + $this->geometry->paddingLeft +
        $this->width +
        $this->geometry->paddingRight + $this->geometry->borderRight;
    }
    if ($this->geometry->width === 'calculated') {
      if (is_int($this->geometry->height)) {
        $this->geometry->width = (int)($this->geometry->height * $this->width / $this->height);
      }
    }
    $this->geometry->limitateWidth();
    $this->geometry->setDerivedWidths();
  }

  protected function calculateHeights() {
    if ($this->geometry->height < 0) {
      $this->geometry->height = $this->ancestor->geometry->innerHeight + $this->height;
    }
    if ($this->geometry->height === 'content') {
      $this->geometry->height =
        $this->geometry->borderTop + $this->geometry->paddingTop +
        $this->height +
        $this->geometry->paddingBottom + $this->geometry->borderBottom;
    }
    if ($this->geometry->height === 'calculated') {
      $this->geometry->height = (int)($this->geometry->width * $this->height / $this->width);
    }
    $this->geometry->limitateHeight();
    $this->geometry->setDerivedHeights();
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent, 0);
  }

  protected function layout() {
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
  }

  protected function draw() {
    $w = $this->geometry->innerWidth;
    $h = $this->geometry->innerHeight;
    $img = imagescale($this->img, $w, $h);
    // convert to RGBA (little-endian)
    $size = $w * $h * 4;
    $this->rgba = \FFI::new("uint8_t[{$size}]");
    $offset = 0;
    for ($y = 0; $y < $h; $y++) {
      for ($x = 0; $x < $w; $x++) {
        $c = imagecolorat($img, $x, $y);
        $a = 255 - intdiv((($c >> 24) & 0x7F) * 255, 127);
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = $c & 0xFF;
        $this->rgba[$offset++] = $a;
        $this->rgba[$offset++] = $b;
        $this->rgba[$offset++] = $g;
        $this->rgba[$offset++] = $r;
      }
    }
    // copy RGBA data to a new surface
    $sdl = SDL::$instance->sdl;
    $bgcolor = $this->style->get('backgroundColor');
    $surface = $sdl->SDL_CreateSurface($this->geometry->width, $this->geometry->height, SDL::SDL_PIXELFORMAT_RGBA8888);
    $sdl->SDL_LockSurface($surface);
    $pixels = $surface->pixels; // void* pointer
    $pitch  = $surface->pitch;  // int
    $srcStride = $w * 4;
    $srcOffset = 0;
    $dstRef = $surface->pixels + ($this->geometry->borderLeft + $this->geometry->paddingLeft) * 4;
    $vOffset = $this->geometry->borderTop + $this->geometry->paddingTop;
    for ($y = $vOffset; $y < $h + $vOffset; $y++) {
      $dst = \FFI::cast("uint8_t*", $dstRef + $y * $surface->pitch);
      \FFI::memcpy($dst, \FFI::addr($this->rgba[$srcOffset]), $srcStride);
      $srcOffset += $srcStride;
    }
    $sdl->SDL_UnlockSurface($surface);
    // create a Texture from the surface
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $bgcolor, $surface);
    $sdl->SDL_DestroySurface($surface);
  }

  protected function load() {
    $this->img = imagecreatefrompng($this->value);
    if (!$this->img) {
      throw new \Exception("Failed to load image: {$this->value}");
    }
    imagepalettetotruecolor($this->img);
    imagesavealpha($this->img, true);
    $this->width  = imagesx($this->img);
    $this->height = imagesy($this->img);
  }

}
