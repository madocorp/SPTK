<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\Style;
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
$reader = new XMLReader();
$reader->XML('<List highlightSelection="false"><Item>One</Item></List>');
$reader->read();
$parser = new SPTK\Widgets\List\Parser();
$parser->validateAttributes($reader, []);
$xmlList = $parser->parse($reader, $style)->widget;
$xmlList->paint($writer);
if ($grid->cell(0, 0)->fg != $style->foreground) {
  throw new RuntimeException('XML List did not apply highlightSelection="false".');
}
$reader->close();
echo "List cursor row checks passed.\n";
