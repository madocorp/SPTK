<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Style, Widget};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\CheckboxArray\CheckboxArray;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\RadioButton\RadioButton;

/** Assert a fixed-title behavior. */
function expectTitle(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Construct a key press accepted by the list and choice input handlers. */
function titleKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false]];
}

/** Paint an item widget at a specified tile size. */
function titleGrid(Widget $widget, int $width, int $height): Grid {
  $grid = new Grid($width, $height);
  $widget->paint(new GridWriter($grid, new Tile(0, 0, $width, $height)));
  return $grid;
}

/** Read one rendered row as glyphs. */
function titleRow(Grid $grid, int $y): string {
  $text = '';
  for ($x = 0; $x < $grid->width(); $x++) {
    $text .= $grid->cell($x, $y)->glyph;
  }
  return $text;
}

/** Parse a titled widget using its real XML attribute validation and item parser. */
function titleXml(string $name, string $title): Widget {
  $reader = new XMLReader();
  $reader->XML('<' . $name . ' title="' . htmlspecialchars($title, ENT_QUOTES | ENT_XML1) . '"><Item value="one">One</Item><Item value="two">Two</Item></' . $name . '>');
  $reader->read();
  $class = 'SPTK\\Widgets\\' . $name . '\\Parser';
  $parser = new $class();
  $parser->validateAttributes($reader, []);
  return $parser->parse($reader, new Style())->widget;
}

foreach (['List', 'RadioButton', 'CheckboxArray'] as $name) {
  $widget = titleXml($name, 'Choices');
  expectTitle($widget->preferredHeight(), 3, $name . ' title adds one preferred row');
  $grid = titleGrid($widget, 14, 3);
  expectTitle(rtrim(titleRow($grid, 0)), 'Choices', $name . ' XML title appears above items');
  expectTitle($grid->cell(0, 0)->fg == (new Style())->highlight, true, $name . ' inherited highlight title color');
  expectTitle(str_contains(titleRow($grid, 1), 'One'), true, $name . ' first item appears below title');
  $widget->emit('activate');
  titleGrid($widget, 14, 2);
  $widget->handleInput(titleKey(SDL::KEY_DOWN));
  $widget->handleInput(titleKey(SDL::KEY_SPACE));
  $grid = titleGrid($widget, 14, 2);
  expectTitle(rtrim(titleRow($grid, 0)), 'Choices', $name . ' title stays fixed while scrolling');
  expectTitle($grid->cell(13, 1)->glyph, '▲', $name . ' scroll mark stays below title');
  expectTitle($widget->getValue(), $widget instanceof CheckboxArray ? ['two'] : 'two', $name . ' selection ignores title row');
  $oneRow = titleGrid($widget, 14, 1);
  expectTitle(rtrim(titleRow($oneRow, 0)), 'Choices', $name . ' title-only tile paints safely');
  $widget->handleInput(titleKey(SDL::KEY_HOME));
  $widget->handleInput(titleKey(SDL::KEY_SPACE));
  expectTitle($widget->getValue(), $widget instanceof CheckboxArray ? ['one', 'two'] : 'one', $name . ' hidden items retain selection behavior');
  titleGrid($widget, 0, 0);
  $blank = titleXml($name, '');
  expectTitle($blank->preferredHeight(), 3, $name . ' empty title reserves a row');
  expectTitle(titleRow(titleGrid($blank, 4, 1), 0), '    ', $name . ' empty title paints a blank row');
  $wide = titleXml($name, '界界界');
  $clipped = titleGrid($wide, 3, 1);
  expectTitle($clipped->cell(2, 0)->glyph, ' ', $name . ' title clips complete wide glyphs');
  $longTitle = titleXml($name, 'A much wider title than the items');
  expectTitle($longTitle->preferredWidth(), 33, $name . ' preferred width includes title');
  foreach (["bad\ntitle", "bad\ttitle", "\xff"] as $invalid) {
    try {
      $class = $widget::class;
      new $class(title: $invalid);
      throw new RuntimeException($name . ' accepted invalid title');
    } catch (InvalidArgumentException $error) {
    }
  }
}

$items = ['One', 'Two', 'Three', 'Four', 'Five'];
foreach ([new ListView($items, title: 'Title'), new RadioButton($items, title: 'Title'), new CheckboxArray($items, title: 'Title')] as $widget) {
  $widget->emit('activate');
  titleGrid($widget, 14, 3);
  $widget->handleInput(titleKey(SDL::KEY_PAGEDOWN));
  expectTitle($widget->cursorPosition(), 1, 'Page Down uses two item rows below the title');
  $widget->handleInput(titleKey(SDL::KEY_PAGEDOWN));
  expectTitle($widget->cursorPosition(), 3, 'Repeated Page Down advances one item page');
  expectTitle(rtrim(titleRow(titleGrid($widget, 14, 3), 0)), 'Title', 'Paging leaves the title fixed');
}

$list = new ListView(['One', 'Two', 'Three'], title: 'Title');
$list->emit('activate');
$grid = titleGrid($list, 14, 4);
$writer = new GridWriter($grid, new Tile(0, 0, 14, 4));
$grid->beginUpdate();
$list->handleInput(titleKey(SDL::KEY_DOWN));
expectTitle($list->paintUpdate($writer), true, 'Titled List supports partial row updates');
$dirtyRows = array_unique(array_column($grid->dirtyCells(), 1));
sort($dirtyRows);
expectTitle($dirtyRows, [1, 2], 'Partial item updates are offset below the fixed title');
$full = titleGrid($list, 14, 4);
for ($y = 0; $y < 4; $y++) {
  for ($x = 0; $x < 14; $x++) {
    expectTitle($grid->cell($x, $y) == $full->cell($x, $y), true, 'Partial titled paint matches full paint');
  }
}
expectTitle($list->paintUpdate(new GridWriter(new Grid(14, 1), new Tile(0, 0, 14, 1))), false, 'Resizing requires a full List paint');
$list->setFilter('missing');
$empty = titleGrid($list, 30, 3);
expectTitle(rtrim(titleRow($empty, 0)), 'Title', 'Title remains above the no-results message');
expectTitle(str_starts_with(titleRow($empty, 1), '(no results'), true, 'No-results message starts in the item area');

foreach ([new ListView(), new RadioButton(), new CheckboxArray()] as $widget) {
  expectTitle($widget->preferredHeight(), 1, 'Untitled empty widget keeps its original preferred height');
}
foreach ([new ListView(title: 'Title'), new RadioButton(title: 'Title'), new CheckboxArray(title: 'Title')] as $widget) {
  expectTitle($widget->preferredHeight(), 2, 'Titled empty widget reserves title and empty item rows');
  expectTitle(rtrim(titleRow(titleGrid($widget, 14, 2), 0)), 'Title', 'Empty widget keeps its title');
}
echo "Item title checks passed\n";
