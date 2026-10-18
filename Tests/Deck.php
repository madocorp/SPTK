<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\Style;
use SPTK\Events\EventContext;
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\Grid;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Empty\Placeholder;

/** Stand-in for the demo controller used by deck.xml. */
final class Controller {

  public static array $notifications = [];

  /** Ignore demo list changes. */
  public static function deckFruitChanged(EventContext $event): void {
  }

  /** Return a fixed summary so painting can be checked. */
  public static function deckSummary(?string $id): string {
    return "Sum {$id}\nvalue";
  }

}

/** Assert a named deck result. */
function expectDeck(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Return the grid height of a tile found by layout or widget id. */
function deckHeight(LayoutNode $root, string $id): int {
  $node = $root->findNode($id);
  if ($node !== null) {
    return $node->grid()->height;
  }
  foreach ($root->leaves() as $leaf) {
    if ($leaf->instance()->id() === $id) {
      return $leaf->grid()->height;
    }
  }
  throw new RuntimeException("Tile not found: {$id}");
}

/** Read one grid row as text. */
function deckRow(Grid $grid, Tile $tile, int $row): string {
  $text = '';
  for ($x = $tile->x; $x < $tile->x + $tile->width; $x++) {
    $text .= $grid->cell($x, $tile->y + $row)->glyph;
  }
  return rtrim($text);
}

/** Create one key press event. */
function deckKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0]];
}

// Sizes: the first deck tile starts open, the others use their closed height.
$screen = (new SPTK\XmlParser\ScreenParser())->parse('deck.xml', new Style(), 'deck', 'Deck');
$size = new Tile(0, 0, 60, 20);
$screen->measureGrid($size);
$cards = $screen->layout->findNode('deck-cards');
expectDeck(deckHeight($cards, 'deck-fruit'), 2, 'closed list height');
expectDeck(deckHeight($cards, 'deck-person'), 2, 'closed layout height');
expectDeck(deckHeight($cards, 'deck-notes'), 20 - 1 - 1 - 1 - 2 - 1 - 2, 'open editor takes the rest');
$columns = $screen->layout->findNode('deck-columns');
expectDeck($columns->leaves()[0]->grid()->width > 4, true, 'first horizontal tile starts open');
expectDeck($columns->leaves()[1]->grid()->width, 4, 'second horizontal tile stays closed');
// Selecting a leaf in a closed tile opens it and closes the open sibling.
$fruit = null;
foreach ($screen->layout->leaves() as $leaf) {
  if ($leaf->instance()->id() === 'deck-fruit') {
    $fruit = $leaf;
  }
}
$screen->selectLeaf($fruit);
expectDeck($screen->takeLayoutChange(), true, 'select reports a deck change');
expectDeck($screen->takeLayoutChange(), false, 'deck change is reported once');
$screen->measureGrid($size);
expectDeck(deckHeight($cards, 'deck-notes'), 2, 'previous tile closes');
expectDeck(deckHeight($cards, 'deck-fruit') > 2, true, 'selected tile opens');
// Selecting a leaf inside a closed layout tile opens the whole layout.
$first = null;
foreach ($screen->layout->leaves() as $leaf) {
  if ($leaf->instance()->id() === 'deck-first') {
    $first = $leaf;
  }
}
$screen->selectLeaf($first);
$screen->measureGrid($size);
expectDeck(deckHeight($cards, 'deck-person') > 2, true, 'layout tile opens for its child');
expectDeck(deckHeight($cards, 'deck-fruit'), 2, 'list closes again');
// Code can open a tile by id; an unknown id reports false.
expectDeck($screen->openDeckTile('deck-notes'), true, 'open by widget id');
expectDeck($screen->openDeckTile('missing'), false, 'unknown id');
$screen->measureGrid($size);
expectDeck(deckHeight($cards, 'deck-notes') > 2, true, 'opened from code');
// Closed tiles with a summary paint it; tiles without one paint their cut content.
$grid = new Grid(60, 20);
$screen->paint($grid);
$fruitGrid = $fruit->grid();
expectDeck(deckRow($grid, $fruitGrid, 0), 'Sum deck-fruit', 'leaf summary title');
expectDeck(deckRow($grid, $fruitGrid, 1), 'value', 'leaf summary text');
$personGrid = $cards->findNode('deck-person')->grid();
expectDeck(deckRow($grid, $personGrid, 0), 'Sum deck-person', 'layout summary title');
// Expand and collapse notifications reach the widgets of the changed tiles.
$log = [];
$node = new LayoutNode('vertical', '1*', '1*');
foreach (['a', 'b'] as $id) {
  $widget = new Placeholder(new SPTK\Core\Color(0, 0, 0));
  $widget->setId($id);
  $widget->on('expand', function() use (&$log, $id): void {
    $log[] = "expand {$id}";
  });
  $widget->on('collapse', function() use (&$log, $id): void {
    $log[] = "collapse {$id}";
  });
  $leaf = new LayoutLeaf('Empty', '', '1', $widget);
  $leaf->setDeck('1*', 'height');
  $node->addLeaf($leaf);
}
expectDeck($node->leaves()[0]->isDeckOpen(), true, 'first code-built tile opens');
expectDeck($node->openDeckFor($node->leaves()[1]), true, 'open second tile');
expectDeck($log, ['collapse a', 'expand b'], 'notification order');
expectDeck($node->openDeckFor($node->leaves()[1]), false, 'open tile needs no change');
echo "Deck checks passed\n";
