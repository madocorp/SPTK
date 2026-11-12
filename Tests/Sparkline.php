<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Rendering\Sparkline;
use SPTK\Core\Style;
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\Widgets\Sparkline\Parser;

if (Sparkline::render([0, 50, 100], 100, 3) !== '▁▅█') {
  throw new RuntimeException('Sparkline must map samples to eight levels.');
}
if (Sparkline::render([0.5], 0.5, 1) !== '█') {
  throw new RuntimeException('Fractional maximum must use its actual scale.');
}
if (Sparkline::render([], 100, 8) !== '········'
  || Sparkline::render([0], 100, 8) !== '·······▁'
  || Sparkline::render([0, 50], 100, 8) !== '······▁▅') {
  throw new RuntimeException('New samples must enter a fixed-width chart from the right.');
}
if (Sparkline::render([12], 100, 16, '[ 1]', true, '%') !== '[ 1] ·····▂ 12% ') {
  throw new RuntimeException('Label, slots, and value must have one separating space.');
}
if (Sparkline::render([0, 25, 50, 75, 100], 100, 2) !== '▆█') {
  throw new RuntimeException('Sparkline must keep the newest samples when narrow.');
}
if (Sparkline::render([10], 0, 0) !== '') {
  throw new RuntimeException('A zero-width sparkline must be empty.');
}
$reader = new XMLReader();
$reader->XML('<Sparkline samples="0,50,100" max="100" label="[12]" showValue="true" unit="%" />');
$reader->read();
$parser = new Parser();
$parser->validateAttributes($reader, []);
$widget = $parser->parse($reader, new Style())->widget;
$grid = new Grid(16, 1);
$writer = new GridWriter($grid, new Tile(0, 0, 16, 1));
$widget->paint($writer);
$line = '';
for ($x = 0; $x < 16; $x++) {
  $line .= $grid->cell($x, 0)->glyph;
}
if ($line !== '[12] ···▁▅█ 100%') {
  throw new RuntimeException('Sparkline widget must render label, fixed chart slots, and the latest value.');
}
$widget->setSamples([25, 75]);
$widget->paint($writer);
$line = '';
for ($x = 0; $x < 16; $x++) {
  $line .= $grid->cell($x, 0)->glyph;
}
if ($line !== '[12] ····▃▆ 75% ') {
  throw new RuntimeException('Sparkline widget must shift new samples into the same fields.');
}

echo "Sparkline checks passed\n";
