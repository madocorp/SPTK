<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';

use SPTK\Core\Style;
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Text\{Parser, Text};

/** Fail a titled Text check. */
function titleCheck(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/** Read one row of painted grid cells. */
function titleRow(Grid $grid, int $y, int $width): string {
  $row = '';
  for ($x = 0; $x < $width; $x++) {
    $row .= $grid->cell($x, $y)->glyph;
  }
  return $row;
}

$reader = new XMLReader();
$reader->XML('<Text title="About">First line' . "\n" . 'Second line</Text>');
$reader->read();
$parser = new Parser();
$parser->validateAttributes($reader, []);
$text = $parser->parse($reader, new Style())->widget;
titleCheck($text instanceof Text, 'The XML parser must create a Text widget.');
$grid = new Grid(12, 3);
$writer = new GridWriter($grid, new Tile(0, 0, 12, 3));
$text->paint($writer);
titleCheck(str_starts_with(titleRow($grid, 0, 12), 'About') && str_starts_with(titleRow($grid, 1, 12), 'First line') && str_starts_with(titleRow($grid, 2, 12), 'Second line'), 'The title must reserve a row above text content.');
$text->emit('activate');
$text->paint($writer);
$text->handleInput((object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_DOWN, 'mod' => 0]]);
titleCheck($text->paintUpdate($writer) && str_starts_with(titleRow($grid, 0, 12), 'About'), 'Cursor updates must leave the title row intact.');
echo "Text title checks passed.\n";
