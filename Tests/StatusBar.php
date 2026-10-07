<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\StatusBar\StatusBar;
use SPTK\Widgets\TextEditor\TextEditor;

/** Assert a widget tip or status transition. */
function expectStatus(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Build an SDL key event without opening a native window. */
function statusKey(int $key, int $mod = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod, 'repeat' => false]];
}

$editor = new TextEditor(label: 'SOURCE');
$editor->setTips('Custom editor focus.', 'Custom editor active.');
$bar = new StatusBar(new Style());
$list = new ListView(['A', 'B'], multiple: true, filterable: false, searchable: false, reorderable: true);
$root = new LayoutNode('horizontal', '', '');
$root->addLeaf(new LayoutLeaf('TextEditor', '1*', '', $editor));
$root->addLeaf(new LayoutLeaf('List', '1*', '', $list));
$root->addLeaf(new LayoutLeaf('StatusBar', '1*', '', $bar));
$screen = new Screen($root);
$screen->measureGrid(new Tile(0, 0, 72, 6));
expectStatus($bar->text(), 'Custom editor focus.', 'focus override appears automatically');
$grid = new \SPTK\Rendering\Grid(72, 6);
$screen->paint($grid);
$statusTile = $root->leaves()[2]->grid();
$statusCell = $grid->cell($statusTile->x, $statusTile->y);
expectStatus((array)$statusCell->fg, (array)(new Style())->foreground->darkened(), 'unselected status text is dimmed');
expectStatus((array)$statusCell->bg, (array)(new Style())->background->darkened(), 'unselected status background is dimmed');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
$screen->paint($grid);
$statusCell = $grid->cell($statusTile->x, $statusTile->y);
expectStatus((array)$statusCell->fg, (array)(new Style())->foreground, 'selected status text is bright');
expectStatus((array)$statusCell->bg, (array)(new Style())->background, 'selected status background is bright');
$screen->handleEvent(statusKey(SDL::KEY_LEFT));
$screen->handleEvent(statusKey(SDL::KEY_LEFT));
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($bar->text(), 'Custom editor active.', 'active override replaces focus tip');
$bar->notify("Saved\nfile.md");
expectStatus($bar->text(), 'Saved file.md', 'real status message replaces tip temporarily');
$bar->warning('Careful');
expectStatus($bar->kind(), 'warning', 'warning uses its own color mode');
$statusGrid = new \SPTK\Rendering\Grid(24, 1);
$bar->paint(new \SPTK\Rendering\GridWriter($statusGrid, new Tile(0, 0, 24, 1)));
expectStatus((array)$statusGrid->cell(0, 0)->fg, (array)(new Style())->selected, 'warning renders in selected color');
$bar->error('Cannot save');
expectStatus($bar->kind(), 'error', 'error uses its own color mode');
$bar->paint(new \SPTK\Rendering\GridWriter($statusGrid, new Tile(0, 0, 24, 1)));
expectStatus((array)$statusGrid->cell(0, 0)->fg, (array)(new Style())->error, 'error renders in error color');
$decision = '';
$bar->confirm('Proceed?', function () use (&$decision) { $decision = 'yes'; }, function () use (&$decision) { $decision = 'no'; }, function () use (&$decision) { $decision = 'cancel'; });
expectStatus($bar->confirming(), true, 'confirmation stays pending');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus($bar->confirming(), true, 'confirmation consumes unrelated navigation');
$screen->handleEvent(statusKey(ord('n')));
expectStatus($bar->confirming(), false, 'N resolves confirmation');
expectStatus($decision, 'no', 'N invokes the decline callback');
expectStatus($bar->kind(), 'notice', 'resolved confirmation returns to normal color');
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($bar->text(), 'Custom editor focus.', 'tip returns after release');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus($bar->text(), 'Return opens list. Arrow keys move between tiles.', 'list focus tip appears');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus(str_contains($bar->text(), 'Space toggles it'), true, 'multiple list describes Space');
expectStatus(str_contains($bar->text(), 'Shift+Up/Down reorders'), true, 'reorderable list describes shortcut');
expectStatus(str_contains($bar->text(), 'type to find'), false, 'nonsearchable list omits search shortcut');
$list->setTips('One XML tip.');
expectStatus($list->tip(true), 'One XML tip.', 'single override covers active state');
$list->setTips('Focus tip.', 'Active tip.');
expectStatus($list->tip(true), 'Active tip.', 'active override wins');
try {
  $list->setTips("Bad\ntext");
  throw new RuntimeException('Invalid multiline tip should be rejected.');
} catch (InvalidArgumentException $error) {
  expectStatus($list->tip(true), 'Active tip.', 'invalid tip leaves existing overrides intact');
}
echo "Status bar checks passed\n";
