<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Texture;
use \SPTK\SDLWrapper\SDL;

class Image extends Element {

  public $value = false;
  protected \GdImage $img;
  protected $width;
  protected $height;
  protected $sourceX = 0;
  protected $sourceY = 0;
  protected $sourceWidth = null;
  protected $sourceHeight = null;
  protected string $fit = 'stretch';

  public function getAttributeList(): array {
    return ['value', 'fit'];
  }

  public function setFit($value): void {
    $value = (string)$value;
    $this->fit = in_array($value, ['stretch', 'contain'], true) ? $value : 'stretch';
  }

  public function setValue($value): void {
    if (strpos($value, '/') !== 0) {
      if (defined('APP_PATH')) {
        $dir = dirname(APP_PATH);
        $this->value = "{$dir}/{$value}";
      } else {
        $this->value = getcwd() . '/' . $value;
      }
    } else {
      $this->value = $value;
    }
    if (!file_exists($this->value)) {
      throw new \Exception("Image does not exist: {$this->value}");
    }
    $this->load();
  }

  public function setBase64($data) {
    if (strpos($data, 'data:') === 0) {
      $separator = strpos($data, ',');
      if ($separator === false) {
        throw new \InvalidArgumentException('Invalid image data URI.');
      }
      $data = substr($data, $separator + 1);
    }
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
      throw new \InvalidArgumentException('Invalid base64 image data.');
    }
    $img = imagecreatefromstring($decoded);
    if ($img === false) {
      throw new \InvalidArgumentException('Unsupported or invalid image data.');
    }
    $this->value = false;
    $this->setImage($img);
  }

  public function setBytes(string $data): void {
    $img = imagecreatefromstring($data);
    if ($img === false) {
      throw new \InvalidArgumentException('Unsupported or invalid image data.');
    }
    $this->value = false;
    $this->setImage($img);
  }

  public function setRawPixels(string $data, int $width, int $height, int $channels): void {
    if ($width <= 0 || $height <= 0 || !in_array($channels, [3, 4], true)) {
      throw new \InvalidArgumentException('Invalid raw image dimensions.');
    }
    if (strlen($data) < $width * $height * $channels) {
      throw new \InvalidArgumentException('Raw image data is shorter than expected.');
    }
    $img = imagecreatetruecolor($width, $height);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        $r = ord($data[$offset++]);
        $g = ord($data[$offset++]);
        $b = ord($data[$offset++]);
        $alpha = 0;
        if ($channels === 4) {
          $alpha = 127 - intdiv(ord($data[$offset++]) * 127, 255);
        }
        imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $r, $g, $b, $alpha));
      }
    }
    $this->value = false;
    $this->setImage($img);
  }

  public function setSourceRect(int $x, int $y, ?int $width = null, ?int $height = null): void {
    $this->sourceX = max(0, $x);
    $this->sourceY = max(0, $y);
    $this->sourceWidth = $width === null ? null : max(1, $width);
    $this->sourceHeight = $height === null ? null : max(1, $height);
  }

  public function getSourceWidth(): int {
    return $this->sourceWidth ?? max(1, $this->width - $this->sourceX);
  }

  public function getSourceHeight(): int {
    return $this->sourceHeight ?? max(1, $this->height - $this->sourceY);
  }

  protected function calculateWidths(): void {
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

  protected function calculateHeights(): void {
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

  protected function layout(): void {
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
  }

  protected function draw(): void {
    $w = $this->geometry->innerWidth;
    $h = $this->geometry->innerHeight;
    $sourceWidth = $this->getSourceWidth();
    $sourceHeight = $this->getSourceHeight();
    $drawWidth = $w;
    $drawHeight = $h;
    if ($this->fit === 'contain') {
      $scale = min($w / $sourceWidth, $h / $sourceHeight);
      $drawWidth = max(1, (int)round($sourceWidth * $scale));
      $drawHeight = max(1, (int)round($sourceHeight * $scale));
    }
    $img = imagecrop($this->img, [
      'x' => $this->sourceX,
      'y' => $this->sourceY,
      'width' => $sourceWidth,
      'height' => $sourceHeight
    ]);
    if ($img === false) {
      $img = $this->img;
    }
    $img = imagescale($img, $drawWidth, $drawHeight);
    if ($img === false) {
      throw new \Exception('Failed to scale image.');
    }
    // convert to RGBA (little-endian)
    $size = $drawWidth * $drawHeight * 4;
    $this->rgba = \FFI::new("uint8_t[{$size}]");
    $offset = 0;
    for ($y = 0; $y < $drawHeight; $y++) {
      for ($x = 0; $x < $drawWidth; $x++) {
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
    for ($y = 0; $y < $this->geometry->height; $y++) {
      $dst = \FFI::cast("uint8_t*", $surface->pixels + $y * $surface->pitch);
      $offset = 0;
      for ($x = 0; $x < $this->geometry->width; $x++) {
        $dst[$offset++] = $bgcolor[3] ?? 0xff;
        $dst[$offset++] = $bgcolor[2];
        $dst[$offset++] = $bgcolor[1];
        $dst[$offset++] = $bgcolor[0];
      }
    }
    $pixels = $surface->pixels; // void* pointer
    $pitch  = $surface->pitch;  // int
    $srcStride = $drawWidth * 4;
    $srcOffset = 0;
    $hOffset = $this->geometry->borderLeft + $this->geometry->paddingLeft + (int)floor(($w - $drawWidth) / 2);
    $vOffset = $this->geometry->borderTop + $this->geometry->paddingTop + (int)floor(($h - $drawHeight) / 2);
    $dstRef = $surface->pixels + $hOffset * 4;
    for ($y = $vOffset; $y < $drawHeight + $vOffset; $y++) {
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
    $data = file_get_contents($this->value);
    if ($data === false) {
      throw new \Exception("Failed to load image: {$this->value}");
    }
    $img = imagecreatefromstring($data);
    if ($img === false) {
      throw new \Exception("Failed to load image: {$this->value}");
    }
    $this->setImage($img);
  }

  private function setImage(\GdImage $img) {
    $this->img = $img;
    imagepalettetotruecolor($this->img);
    imagesavealpha($this->img, true);
    $this->width = imagesx($this->img);
    $this->height = imagesy($this->img);
  }

}
