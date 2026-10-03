<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\Style;
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\Widgets\ProgressBar\ProgressBar;

/** Check one progress bar behavior. */
function expectProgress(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

$style = new Style();
$bar = new ProgressBar(5, 10, title: 'Build', style: $style);
expectProgress($bar->label(), '50%', 'default percentage');
expectProgress($bar->getValue(), 5.0, 'standard value getter');
expectProgress($bar->ratio(), 0.5, 'half-complete ratio');
expectProgress($bar->preferredHeight(), 2, 'title reserves one row');
expectProgress($bar->canActivate(), false, 'progress is read-only');
$grid = new Grid(20, 3);
$bar->paint(new GridWriter($grid, new Tile(0, 0, 20, 3)));
expectProgress($grid->cell(0, 0)->glyph, 'B', 'title occupies first row');
expectProgress($grid->cell(0, 0)->fg == $style->highlight, true, 'title uses highlight color');
expectProgress($grid->cell(0, 1)->bg == $style->selected, true, 'filled portion uses selection background');
expectProgress($grid->cell(9, 1)->bg == $style->selected, true, 'bar fills exactly one row');
expectProgress($grid->cell(10, 1)->bg == $style->background, true, 'unfilled portion uses background');
expectProgress($grid->cell(9, 2)->bg == $style->background, true, 'extra tile row keeps normal background');
expectProgress($grid->cell(8, 1)->fg == $style->background, true, 'label in fill uses reversed ink');
expectProgress($grid->cell(10, 1)->fg == $style->foreground, true, 'label outside fill uses normal ink');
$bar->setDisplay('fraction');
expectProgress($bar->label(), '5 / 10', 'fraction label');
$bar->setProgress(2.5, 5);
expectProgress($bar->label(), '2.5 / 5', 'decimal fraction label');
$bar->setValue(99);
expectProgress($bar->value(), 5.0, 'value clamps to maximum');
$bar->setMaximum(0);
expectProgress([$bar->value(), $bar->ratio()], [0.0, 0.0], 'zero maximum empties bar');
$bar->setText("Writing\nfiles");
expectProgress([$bar->display(), $bar->label()], ['text', 'Writing files'], 'text mode normalizes line breaks');
$bar->setTitle("Current\tphase");
expectProgress($bar->title(), 'Current phase', 'title normalizes tabs');
try {
  $bar->setProgress(1, -1);
  throw new RuntimeException('negative maximum accepted');
} catch (InvalidArgumentException $error) {
  expectProgress([$bar->value(), $bar->maximum()], [0.0, 0.0], 'invalid progress leaves state unchanged');
}
$wide = new ProgressBar(1, 2, 'text', '界界');
$wideGrid = new Grid(3, 1);
$wide->paint(new GridWriter($wideGrid, new Tile(0, 0, 3, 1)));
expectProgress([$wideGrid->cell(0, 0)->glyph, $wideGrid->cell(0, 0)->width], ['界', 2], 'wide label glyph stays intact');
expectProgress($wideGrid->cell(1, 0)->width, 0, 'wide label continuation is kept');
expectProgress($wideGrid->cell(1, 0)->bg == $style->selected, true, 'wide glyph keeps one color across fill boundary');
expectProgress($wideGrid->cell(2, 0)->glyph, ' ', 'clipped wide glyph becomes blank');
$screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
$bars = [];
foreach ($screens[5]->layout->leaves() as $leaf) {
  if ($leaf->instance() instanceof ProgressBar) {
    $bars[] = $leaf->instance();
  }
}
expectProgress(count($bars), 3, 'demo screen has three progress modes');
expectProgress($bars[0]->title(), 'Build assets', 'XML title parsed');
expectProgress($bars[0]->label(), '65%', 'XML percent parsed');
expectProgress($bars[1]->label(), '42 / 120', 'XML fraction parsed');
expectProgress($bars[2]->label(), 'Writing files', 'XML custom text parsed');
$reader = new XMLReader();
$reader->XML('<ProgressBar title="Phase" value="1" max="2">Working</ProgressBar>');
$reader->read();
$parser = new SPTK\Widgets\ProgressBar\Parser();
$parser->validateAttributes($reader, []);
$fromBody = $parser->parse($reader, new Style())->widget;
expectProgress([$fromBody->title(), $fromBody->display(), $fromBody->label()], ['Phase', 'text', 'Working'], 'XML body selects text mode');
$reader = new XMLReader();
$reader->XML('<ProgressBar value="1e999" />');
$reader->read();
try {
  $parser->parse($reader, new Style());
  throw new RuntimeException('nonfinite XML progress accepted');
} catch (RuntimeException $error) {
  expectProgress(str_contains($error->getMessage(), 'finite number'), true, 'nonfinite XML progress rejected');
}
echo "Progress bar checks passed\n";
