<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\RasterImage;
use SPTK\SDLWrapper\PNG;

/** Check one bulk pixel conversion behavior. */
function expectRaster(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': values differ.');
  }
}

/** Produce reference SDL words using GD's public pixel getters. */
function referenceRaster(GdImage $image): string {
  $result = '';
  $transparent = imagecolortransparent($image);
  for ($y = 0; $y < imagesy($image); $y++) {
    $row = [];
    for ($x = 0; $x < imagesx($image); $x++) {
      $index = imagecolorat($image, $x, $y);
      $color = imagecolorsforindex($image, $index);
      $alpha = $index === $transparent ? 0 : (int)round((127 - $color['alpha']) * 255 / 127);
      $row[] = ($color['red'] << 24) | ($color['green'] << 16) | ($color['blue'] << 8) | $alpha;
    }
    $result .= pack('L*', ...$row);
  }
  return $result;
}

/** Export PNG bytes to check that conversion leaves the caller's save settings alone. */
function rasterPNG(GdImage $image): string {
  ob_start();
  try {
    imagepng($image, null, 0);
    return ob_get_contents();
  } finally {
    ob_end_clean();
  }
}

$image = imagecreatetruecolor(128, 3);
imagealphablending($image, false);
for ($y = 0; $y < 3; $y++) {
  for ($x = 0; $x < 128; $x++) {
    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $x, $y * 70, 255 - $x, $x));
  }
}
$before = rasterPNG($image);
$expected = referenceRaster($image);
$raster = new RasterImage($image);
expectRaster($raster->pixels, $expected, 'all GD alpha levels and RGB channels match');
expectRaster(rasterPNG($image), $before, 'caller pixels and PNG save flags remain unchanged');
expectRaster([$raster->width, $raster->height, strlen($raster->pixels)], [128, 3, 128 * 3 * 4], 'exact packed dimensions');
imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 100, 120, 140, 127));
expectRaster($raster->pixels, $expected, 'raster remains independent of caller mutations');
imagedestroy($image);
$image = imagecreate(3, 2);
$red = imagecolorallocate($image, 255, 0, 0);
$green = imagecolorallocate($image, 0, 255, 0);
$blue = imagecolorallocatealpha($image, 0, 0, 255, 64);
imagesetpixel($image, 1, 0, $green);
imagesetpixel($image, 2, 1, $blue);
expectRaster((new RasterImage($image))->pixels, referenceRaster($image), 'palette colors and partial alpha match');
imagecolortransparent($image, $green);
expectRaster((new RasterImage($image))->pixels, referenceRaster($image), 'palette transparency preserved');
expectRaster(imageistruecolor($image), false, 'caller palette is not converted in place');
imagedestroy($image);
$image = imagecreatetruecolor(3, 2);
$red = imagecolorallocate($image, 255, 0, 0);
$green = imagecolorallocate($image, 0, 255, 0);
imagefill($image, 0, 0, $red);
imagesetpixel($image, 1, 0, $green);
imagecolortransparent($image, $green);
expectRaster((new RasterImage($image))->pixels, referenceRaster($image), 'truecolor transparency key preserved');
imagedestroy($image);
$opaque = imagecreatetruecolor(31, 17);
imagefilledrectangle($opaque, 0, 0, 15, 16, imagecolorallocate($opaque, 10, 120, 250));
imagefilledrectangle($opaque, 16, 0, 30, 16, imagecolorallocate($opaque, 240, 80, 20));
expectRaster((new RasterImage($opaque))->pixels, referenceRaster($opaque), 'opaque odd-sized image matches reference');
imagedestroy($opaque);
$decoder = new PNG();
try {
  $decoder->pixels('invalid PNG data', 1, 1);
  throw new RuntimeException('Invalid PNG accepted.');
} catch (RuntimeException $error) {
  expectRaster(str_contains($error->getMessage(), 'Cannot read PNG pixels'), true, 'invalid native data fails cleanly');
}
echo "Raster image checks passed\n";
