<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\Style;
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Text\{Parser, Text};
use SPTK\Widgets\TextEditor\TextEditor;

/** Assert a painted cell's glyph and optional background red channel. */
function expectCell(Grid $grid, int $x, int $y, string $glyph, string $name, ?int $backgroundRed = null): void {
  $cell = $grid->cell($x, $y);
  if ($cell->glyph !== $glyph || ($backgroundRed !== null && $cell->bg->r !== $backgroundRed)) {
    throw new RuntimeException($name . ': unexpected cell at ' . $x . ', ' . $y);
  }
}

/** Paint a widget into a fresh grid of the requested size. */
function paintText(Text|TextEditor $widget, int $width, int $height): Grid {
  $grid = new Grid($width, $height);
  $widget->paint(new GridWriter($grid, new Tile(0, 0, $width, $height)));
  return $grid;
}

$center = new Text("a\nabc", align: 'center');
$sameValue = new Grid(2, 1);
$sameValue->beginUpdate();
$sameValue->set(0, 0, ' ');
if (count($sameValue->dirtyCells()) !== 1) {
  throw new RuntimeException('Writing an unchanged value must still mark the cell dirty');
}
$grid = paintText($center, 7, 2);
expectCell($grid, 3, 0, 'a', 'centered short row');
expectCell($grid, 2, 1, 'a', 'centered longer row');
expectCell(paintText($center, 9, 2), 4, 0, 'a', 'centered row after resize');
$moving = new Text('abcd');
$moving->emit('activate');
$grid = paintText($moving, 10, 2);
$grid->beginUpdate();
$moving->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_RIGHT, 'mod' => 0]]);
$updated = $moving->paintUpdate(new GridWriter($grid, new Tile(0, 0, 10, 2)));
if (!$updated || count($grid->dirtyCells()) !== 2) {
  throw new RuntimeException('Moving the Text cursor must dirty only two grid cells');
}
$center->emit('activate');
expectCell(paintText($center, 7, 2), 3, 0, 'a', 'centered cursor', 85);
$center->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_END, 'mod' => 0]]);
$center->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_RIGHT, 'mod' => SDL::MOD_SHIFT]]);
expectCell(paintText($center, 7, 2), 4, 0, '¶', 'centered selected newline', 85);
$right = new Text("a\nabc", align: 'right');
$grid = paintText($right, 7, 2);
expectCell($grid, 6, 0, 'a', 'right aligned short row');
expectCell($grid, 4, 1, 'a', 'right aligned longer row');
$wrapped = new Text('one two three', align: 'right');
$grid = paintText($wrapped, 7, 3);
expectCell($grid, 0, 0, 'o', 'right aligned full wrapped row');
expectCell($grid, 2, 1, 't', 'right aligned shorter wrapped row');
$overflow = new Text("abcdefgh\nxy", wrap: false, align: 'right');
$grid = paintText($overflow, 5, 2);
expectCell($grid, 0, 0, 'a', 'overlong row remains scrollable');
expectCell($grid, 3, 1, 'x', 'short row aligns beside overlong row');
$reader = new XMLReader();
$reader->XML('<Text align="right">Hi</Text>');
$reader->read();
$parser = new Parser();
$parser->validateAttributes($reader, []);
$parsedText = $parser->parse($reader, new Style())->widget;
expectCell(paintText($parsedText, 5, 1), 3, 0, 'H', 'Text XML alignment');
try {
  new Text('invalid', align: 'justify');
  throw new RuntimeException('invalid Text alignment accepted');
} catch (InvalidArgumentException $exception) {
  if ($exception->getMessage() !== 'Text align must be left, center, or right.') {
    throw $exception;
  }
}
$reader->XML('<TextEditor align="right">Hi</TextEditor>');
$reader->read();
try {
  (new SPTK\Widgets\TextEditor\Parser())->validateAttributes($reader, []);
  throw new RuntimeException('TextEditor alignment accepted');
} catch (RuntimeException $exception) {
  if ($exception->getMessage() !== "<TextEditor> does not accept the 'align' attribute.") {
    throw $exception;
  }
}
expectCell(paintText(new TextEditor('Hi'), 5, 1), 0, 0, 'H', 'TextEditor stays left aligned');
$window = (new SPTK\XmlParser\XmlParser())->windows[0];
if ($window['state'] !== 'maximized' || $window['screens'][0]->id !== 'text') {
  throw new RuntimeException('Demo must open maximized on the Text screen');
}
$screen = $window['screens'][0];
$screen->measureGrid(new Tile(0, 0, $window['width'], $window['height']));
$grid = new Grid($window['width'], $window['height']);
$screen->paint($grid);
$panels = [];
foreach ($screen->layout->leaves() as $leaf) {
  if ($leaf->instance() instanceof Text) {
    $panels[] = $leaf->grid();
  }
}
if (count($panels) !== 4 || $panels[3]->height !== 1) {
  throw new RuntimeException('Demo Text screen must have three panels and a one-row footer');
}
foreach (array_slice($panels, 0, 3) as $index => $tile) {
  expectCell($grid, $tile->x + $tile->width - 1, $tile->y + $tile->height - 1, '▼', 'demo Text panel ' . $index . ' scrolls');
}
echo "Text alignment checks passed\n";
