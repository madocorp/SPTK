<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/Fixtures/PNG.php';
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\RasterImage;
use SPTK\SDLWrapper\PNG;

/** Check one behavior when the native pixel converter cannot load. */
function expectFallback(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': values differ.');
  }
}

$image = imagecreatetruecolor(128, 2);
imagealphablending($image, false);
$expected = '';
for ($y = 0; $y < 2; $y++) {
  for ($x = 0; $x < 128; $x++) {
    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $x, $y * 80, 255 - $x, $x));
    $alpha = (int)round((127 - $x) * 255 / 127);
    $expected .= pack('L', ($x << 24) | (($y * 80) << 16) | ((255 - $x) << 8) | $alpha);
  }
}
$raster = new RasterImage($image);
expectFallback($raster->pixels, $expected, 'fallback preserves RGB channels, rows, and all alpha levels');
expectFallback([$raster->width, $raster->height], [128, 2], 'fallback keeps source dimensions');
expectFallback(PNG::$attempts, 1, 'missing native library tried once');
expectFallback((new RasterImage($image))->pixels, $expected, 'subsequent conversion still works');
expectFallback(PNG::$attempts, 1, 'subsequent image does not retry unavailable library');
imagedestroy($image);
$image = imagecreate(3, 1);
$red = imagecolorallocate($image, 255, 0, 0);
$green = imagecolorallocate($image, 0, 255, 0);
$blue = imagecolorallocatealpha($image, 0, 0, 255, 64);
imagesetpixel($image, 1, 0, $green);
imagesetpixel($image, 2, 0, $blue);
imagecolortransparent($image, $green);
expectFallback((new RasterImage($image))->pixels, pack('L*', 0xff0000ff, 0x00ff0000, 0x0000ff7e), 'fallback preserves palette transparency');
expectFallback(imageistruecolor($image), false, 'fallback leaves caller palette intact');
imagedestroy($image);
$image = imagecreatetruecolor(1, 1);
$green = imagecolorallocate($image, 0, 255, 0);
imagesetpixel($image, 0, 0, $green);
imagecolortransparent($image, $green);
expectFallback((new RasterImage($image))->pixels, pack('L', 0x00ff0000), 'fallback preserves truecolor transparency key');
imagedestroy($image);
echo "Raster fallback checks passed\n";
