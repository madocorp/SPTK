<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator, Tile};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Empty\Placeholder;
use SPTK\Widgets\Text\Text;

/** Assert a named scroll result. */
function expectScroll(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Create a navigable empty tile with an id. */
function scrollTile(string $id, string $width, string $height): LayoutLeaf {
  $widget = new Placeholder(new Color(0, 0, 0));
  $widget->setId($id);
  return new LayoutLeaf('Empty', $width, $height, $widget);
}

/** Add tiles with separators between them. */
function scrollStack(LayoutNode $node, array $tiles): void {
  foreach ($tiles as $index => $tile) {
    if ($index > 0) {
      $node->addSeparator(new LayoutSeparator());
    }
    $tile instanceof LayoutNode ? $node->addNode($tile) : $node->addLeaf($tile);
  }
}

/** Press an arrow key and measure again when the screen asks for it, as the window does. */
function scrollPress(Screen $screen, int $key, Tile $grid): void {
  $screen->handleEvent((object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0]]);
  if ($screen->takeLayoutChange()) {
    $screen->measureGrid($grid);
  }
}

/** Report whether a tile is fully inside a view on both axes. */
function scrollFullyShown(Tile $tile, Tile $view): bool {
  return $tile->x >= $view->x && $tile->y >= $view->y
    && $tile->x + $tile->width <= $view->x + $view->width && $tile->y + $tile->height <= $view->y + $view->height;
}

// Children that fit split the space as before and do not scroll.
$fits = new LayoutNode('vertical', '1*', '1*');
scrollStack($fits, [scrollTile('a', '1*', '2'), scrollTile('b', '1*', '1*')]);
$fits->measureGrid(new Tile(0, 0, 10, 10));
expectScroll($fits->isScrolling(), false, 'fitting layout does not scroll');
expectScroll($fits->leaves()[1]->grid()->height, 7, 'weighted child takes the rest');
// The majira case: a tall editor at the bottom scrolls, then a short one at the top must be fully shown.
$pane = new LayoutNode('vertical', '1*', '1*');
$cards = [];
for ($index = 0; $index < 8; $index++) {
  $cards[] = scrollTile("card{$index}", '1*', '2');
}
scrollStack($pane, $cards);
$root = new LayoutNode('vertical', '1*', '1*');
$root->addNode($pane);
$screen = new Screen($root);
$grid = new Tile(0, 0, 20, 20);
$tall = scrollTile('tall', '1*', '11');
$pane->replaceChild($cards[6], $tall);
$screen->setLayout($root);
$screen->selectLeaf($tall);
$screen->measureGrid($grid);
expectScroll(scrollFullyShown($tall->grid(), $grid), true, 'tall editor scrolls into view');
expectScroll($pane->scrollOffset() > 0, true, 'tall editor needs an offset');
$pane->replaceChild($tall, $cards[6]);
$screen->setLayout($root);
$screen->selectLeaf($cards[6]);
$screen->measureGrid($grid);
$short = scrollTile('short', '1*', '9');
$pane->replaceChild($cards[0], $short);
$screen->setLayout($root);
$screen->selectLeaf($short);
$screen->measureGrid($grid);
expectScroll($short->grid()->y, 0, 'later editor at the top is fully shown');
expectScroll(scrollFullyShown($short->grid(), $grid), true, 'later editor is not cut');
// Offsets always start at a whole tile.
$pane->setScrollOffset(4);
$screen->measureGrid($grid);
expectScroll($pane->scrollOffset(), 0, 'reveal keeps the selected top tile');
// Arrows walk through hidden tiles before leaving the scrolled layout.
$list = new LayoutNode('vertical', '1*', '1*');
$items = [];
for ($index = 0; $index < 10; $index++) {
  $items[] = scrollTile("item{$index}", '1*', '2');
}
scrollStack($list, $items);
$below = scrollTile('below', '1*', '1');
$outer = new LayoutNode('vertical', '1*', '1*');
scrollStack($outer, [$list, $below]);
$listScreen = new Screen($outer);
$listGrid = new Tile(0, 0, 20, 12);
$listScreen->measureGrid($listGrid);
expectScroll($list->isScrolling(), true, 'list does not fit');
$visited = [];
for ($step = 0; $step < 10; $step++) {
  scrollPress($listScreen, SDL::KEY_DOWN, $listGrid);
  $selected = $listScreen->selectedLeaf();
  $visited[] = $selected->instance()->id();
  expectScroll(scrollFullyShown($selected->grid(), $listGrid), true, 'selected tile is fully shown at step ' . $step);
  if ($list->scrollOffset() > 0 && $selected !== $below) {
    expectScroll($selected->grid()->y + $selected->grid()->height, $list->grid()->y + $list->grid()->height, 'moving down aligns the tile to the bottom at step ' . $step);
  }
}
expectScroll($visited, ['item1', 'item2', 'item3', 'item4', 'item5', 'item6', 'item7', 'item8', 'item9', 'below'], 'down walks the list, then leaves it');
scrollPress($listScreen, SDL::KEY_UP, $listGrid);
expectScroll($listScreen->selectedLeaf()->instance()->id(), 'item9', 'up comes back to the last item');
scrollPress($listScreen, SDL::KEY_UP, $listGrid);
scrollPress($listScreen, SDL::KEY_UP, $listGrid);
scrollPress($listScreen, SDL::KEY_UP, $listGrid);
scrollPress($listScreen, SDL::KEY_UP, $listGrid);
expectScroll($items[5]->grid()->y, $list->grid()->y, 'moving up aligns the tile to the top');
for ($step = 0; $step < 4; $step++) {
  scrollPress($listScreen, SDL::KEY_DOWN, $listGrid);
}
// Marks: the bottom separator carries the hidden count, the top falls back to the layout's own edge row.
$marks = [];
foreach ($list->scrollMarks() as [$x, $y, $glyph]) {
  $marks[$y] = ($marks[$y] ?? '') . $glyph;
}
expectScroll(isset($marks[0]) && str_starts_with($marks[0], '▲'), true, 'top mark on the edge row');
expectScroll(array_key_exists(11, $marks), false, 'no bottom mark when the end is shown');
for ($step = 0; $step < 9; $step++) {
  scrollPress($listScreen, SDL::KEY_UP, $listGrid);
}
$marks = [];
foreach ($list->scrollMarks() as [$x, $y, $glyph]) {
  $marks[$y] = ($marks[$y] ?? '') . $glyph;
}
expectScroll($listScreen->selectedLeaf()->instance()->id(), 'item0', 'up walks back to the first item');
expectScroll($marks[$list->grid()->y + $list->grid()->height] ?? null, ' 7▼ ', 'bottom mark on the separator counts the cut tile too');
// No tile is longer than its parent: a tall tile is cut to the view, and focus moves on with one press.
$long = new LayoutNode('vertical', '1*', '1*');
$text = new LayoutLeaf('Text', '1*', '20', new Text(implode("\n", range(1, 20)), wrap: false));
$after = scrollTile('after', '1*', '2');
scrollStack($long, [$text, $after]);
$longScreen = new Screen($long);
$longGrid = new Tile(0, 0, 10, 6);
$longScreen->measureGrid($longGrid);
expectScroll($text->grid()->height, 6, 'tall tile is cut to the parent');
expectScroll($longScreen->selectedLeaf(), $text, 'long text starts selected');
scrollPress($longScreen, SDL::KEY_DOWN, $longGrid);
expectScroll($longScreen->selectedLeaf(), $after, 'one press moves to the next tile');
expectScroll(scrollFullyShown($after->grid(), $longGrid), true, 'next tile is shown');
$alone = new LayoutNode('horizontal', '1*', '1*');
$wide = scrollTile('wide', '30', '');
$alone->addLeaf($wide);
$alone->measureGrid(new Tile(0, 0, 12, 3));
expectScroll([$wide->grid()->width, $alone->isScrolling()], [12, false], 'a single wide tile is cut and does not scroll');
// Horizontal layouts scroll too, using the min attribute of weighted columns.
$columns = new LayoutNode('horizontal', '1*', '1*');
$columnTiles = [];
for ($index = 0; $index < 5; $index++) {
  $column = scrollTile("col{$index}", '1*', '');
  $column->setMinSize(10);
  $columnTiles[] = $column;
}
scrollStack($columns, $columnTiles);
$columnScreen = new Screen($columns);
$columnGrid = new Tile(0, 0, 25, 4);
$columnScreen->measureGrid($columnGrid);
expectScroll($columns->isScrolling(), true, 'columns with a minimum scroll');
expectScroll($columnTiles[0]->grid()->width, 10, 'columns keep their minimum');
for ($step = 0; $step < 4; $step++) {
  scrollPress($columnScreen, SDL::KEY_RIGHT, $columnGrid);
}
expectScroll($columnScreen->selectedLeaf()->instance()->id(), 'col4', 'right reaches the last column');
expectScroll(scrollFullyShown($columnTiles[4]->grid(), $columnGrid), true, 'last column is shown');
expectScroll($columnTiles[4]->grid()->x + $columnTiles[4]->grid()->width, 25, 'last column aligns to the right edge');
$topRow = array_fill(0, 25, ' ');
foreach ($columns->scrollMarks() as [$x, $y, $glyph]) {
  if ($y === 0) {
    $topRow[$x] = $glyph;
  }
}
expectScroll(rtrim(implode('', $topRow)), '◀3', 'left mark reads normally at the start of the top row');
scrollPress($columnScreen, SDL::KEY_LEFT, $columnGrid);
scrollPress($columnScreen, SDL::KEY_LEFT, $columnGrid);
scrollPress($columnScreen, SDL::KEY_LEFT, $columnGrid);
$topRow = array_fill(0, 25, ' ');
foreach ($columns->scrollMarks() as [$x, $y, $glyph]) {
  if ($y === 0) {
    $topRow[$x] = $glyph;
  }
}
expectScroll(implode('', $topRow), '◀1' . str_repeat(' ', 21) . '2▶', 'both marks share the top row');
// A deck that does not fit gives the open card a full view.
$deck = new LayoutNode('vertical', '1*', '1*');
$deckCards = [];
for ($index = 0; $index < 8; $index++) {
  $card = scrollTile("deck{$index}", '1*', '2');
  $card->setDeck('1*', 'height');
  $deckCards[] = $card;
}
scrollStack($deck, $deckCards);
$deckScreen = new Screen($deck);
$deckGrid = new Tile(0, 0, 20, 12);
$deckScreen->measureGrid($deckGrid);
expectScroll($deck->isScrolling(), true, 'closed cards do not fit');
expectScroll($deckCards[0]->grid()->height, 12, 'open card takes the whole view');
$deckScreen->selectLeaf($deckCards[5]);
$deckScreen->takeLayoutChange();
$deckScreen->measureGrid($deckGrid);
expectScroll($deckCards[5]->grid()->height, 12, 'newly opened card takes the whole view');
expectScroll(scrollFullyShown($deckCards[5]->grid(), $deckGrid), true, 'newly opened card is shown');
expectScroll($deckCards[0]->grid()->height, 2, 'first card closes');
// A stack shown at its end stays there when the view shrinks.
$log = new LayoutNode('vertical', '1*', '1*');
scrollStack($log, [scrollTile('l0', '1*', '3'), scrollTile('l1', '1*', '3'), scrollTile('l2', '1*', '3')]);
$log->setScrollOffset(PHP_INT_MAX);
$log->measureGrid(new Tile(0, 0, 10, 8));
$log->measureGrid(new Tile(0, 0, 10, 4));
expectScroll($log->scrollOffset(), $log->maxScrollOffset(), 'end stays at the end');
echo "Scroll checks passed\n";
