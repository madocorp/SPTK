<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\List\ListView;

$style = new Style();
$list = new ListView(['One', 'Two'], style: $style);
$grid = new Grid(12, 2);
$writer = new GridWriter($grid, new Tile(0, 0, 12, 2));
$list->paint($writer);
if ($grid->cell(0, 0)->fg != $style->selected) {
  throw new RuntimeException('Default List selection lost its selected text color.');
}
$list->emit('activate');
$list->paint($writer);
if ($grid->cell(11, 0)->bg != $style->cursorBackground || $grid->cell(11, 1)->bg != $style->background) {
  throw new RuntimeException('Full paint did not fill only the active cursor row.');
}
$list->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN,
  'key' => (object)['key' => SDL::KEY_DOWN, 'mod' => 0, 'repeat' => false]]);
if (!$list->paintUpdate($writer) || $grid->cell(11, 0)->bg != $style->background
  || $grid->cell(11, 1)->bg != $style->cursorBackground) {
  throw new RuntimeException('Partial paint did not move the full-row cursor.');
}
$list->emit('deactivate');
$list->paint($writer);
if ($grid->cell(11, 1)->bg != $style->background) {
  throw new RuntimeException('Inactive list retained the cursor background.');
}
$plain = new ListView(['One', 'Two'], style: $style, highlightSelection: false);
$plain->paint($writer);
if ($grid->cell(0, 0)->fg != $style->foreground) {
  throw new RuntimeException('List without selection highlighting colored its current item.');
}
$plain->emit('activate');
$plain->paint($writer);
if ($grid->cell(0, 0)->fg != $style->foreground || $grid->cell(11, 0)->bg != $style->cursorBackground) {
  throw new RuntimeException('List without selection highlighting lost its active cursor.');
}
$inactiveColor = new Color(0, 0, 128);
$marked = new ListView([
  ['value' => 'one', 'label' => 'One', 'selected' => true],
  ['value' => 'two', 'label' => 'Two'],
], multiple: true, style: $style, inactiveCursorBackground: $inactiveColor);
$marked->paint($writer);
if ($grid->cell(0, 0)->fg != $style->selected || $grid->cell(11, 0)->bg != $inactiveColor
  || $grid->cell(11, 1)->bg != $style->background) {
  throw new RuntimeException('Inactive cursor background did not preserve pin color or fill only its row.');
}
$marked->emit('activate');
$marked->paint($writer);
if ($grid->cell(11, 0)->bg != $style->cursorBackground) {
  throw new RuntimeException('Active cursor background did not override the inactive color.');
}
$marked->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN,
  'key' => (object)['key' => SDL::KEY_DOWN, 'mod' => 0, 'repeat' => false]]);
if (!$marked->paintUpdate($writer) || $grid->cell(11, 0)->bg != $style->background
  || $grid->cell(11, 1)->bg != $style->cursorBackground) {
  throw new RuntimeException('Moving an active cursor left an incorrect row background.');
}
$marked->emit('deactivate');
$marked->paint($writer);
if ($grid->cell(11, 0)->bg != $style->background || $grid->cell(11, 1)->bg != $inactiveColor) {
  throw new RuntimeException('Inactive cursor background did not follow the current row.');
}
$reader = new XMLReader();
$reader->XML('<List highlightSelection="false" inactiveCursorBackground="#000080"><Item>One</Item></List>');
$reader->read();
$parser = new SPTK\Widgets\List\Parser();
$parser->validateAttributes($reader, []);
$xmlList = $parser->parse($reader, $style)->widget;
$xmlList->paint($writer);
if ($grid->cell(0, 0)->fg != $style->foreground || $grid->cell(11, 0)->bg != $inactiveColor) {
  throw new RuntimeException('XML List did not apply its selection or inactive cursor options.');
}
$reader->close();
echo "List cursor row checks passed.\n";
