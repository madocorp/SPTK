#!/usr/bin/env php
<?php

define('SPTK\DEBUG', false);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'EXAMPLES');
define('SPTK_PATH', realpath(__DIR__ . '/../../..'));

require_once SPTK_PATH . '/Autoload.php';

$image = imagecreatetruecolor(64, 64);
$background = imagecolorallocate($image, 0, 170, 170);
$foreground = imagecolorallocate($image, 255, 255, 255);
imagefilledrectangle($image, 0, 0, 63, 63, $background);
imagefilledrectangle($image, 16, 16, 47, 47, $foreground);
imagepng($image, __DIR__ . '/image.png');
imagedestroy($image);

new SPTK\App(__DIR__ . "/layout.xml", __DIR__ . "/style.xss");
