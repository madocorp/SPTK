<?php

define('APP_DIR', __DIR__);
require APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\Grid;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\ColorInput\ColorInput;

/** Check one color input result. */
function expectColorInput(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/** Create a keyboard event for screen and widget input. */
function colorInputKey(int $key, int $modifiers = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $modifiers, 'repeat' => false]];
}

/** Create one text-input event. */
function colorInputText(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

$colors = ['#ff9000', '#0050ff', '#ff5050', '#604000', '#00bb00', '#bf00bf', '#008c8c', '#b05c00', '#7b55c7', '#cc3974', '#547800', '#5555aa'];
$style = new Style();
$widget = new ColorInput('#ff9000', $colors, $style, 'Color');
expectColorInput($widget->preferredHeight() === 2, 'The inactive input occupies two rows.');
expectColorInput($widget->background() == $style->background && $widget->dimContentWhenUnselected(), 'ColorInput uses normal tile dimming.');
$root = new LayoutNode('vertical', '1*', '1*');
$leaf = new LayoutLeaf('ColorInput', '', 'auto', $widget);
$root->addLeaf($leaf);
$screen = new Screen($root);
$screen->measureGrid(new Tile(0, 0, 20, 8));
$grid = new Grid(20, 8);
$screen->paint($grid);
expectColorInput($grid->cell(0, 0)->bg == $style->background && $grid->cell(0, 0)->fg == $style->highlight, 'The title uses normal Input colors.');
expectColorInput($grid->cell(0, 1)->bg == Color::from('#ff9000'), 'The hex row has the current color background.');
expectColorInput($leaf->grid()->height === 2, 'Auto height initially measures two rows.');
$leaf->paint($grid, false);
expectColorInput($grid->cell(1, 1)->bg == Color::from('#ff9000')->darkened(), 'The hex row darkens when the tile is not selected.');
expectColorInput($grid->cell(0, 0)->bg == $style->background->darkened(), 'The title darkens with the rest of the tile.');
$screen->activateLeaf($leaf);
expectColorInput($widget->paletteMode() && $widget->palettePosition() === 0, 'Activation opens the palette at the current color.');
expectColorInput($widget->preferredHeight() === 8 && $screen->takeLayoutChange(), 'Opening the palette requests its full height.');
$screen->measureGrid(new Tile(0, 0, 20, 8));
expectColorInput($leaf->grid()->height === 8, 'Auto height grows to fit the palette.');
$screen->handleEvent(colorInputKey(SDL::KEY_RIGHT));
$screen->handleEvent(colorInputKey(SDL::KEY_DOWN));
expectColorInput($widget->palettePosition() === 5 && $widget->getValue() === '#bf00bf', 'Arrows move through the color grid.');
$grid = new Grid(20, 8);
$screen->paint($grid);
expectColorInput($grid->cell(0, 0)->bg == $style->background && $grid->cell(0, 0)->fg == $style->highlight, 'Picker mode keeps the normal title.');
expectColorInput($grid->cell(0, 1)->glyph === ' ' && $grid->cell(1, 1)->bg == Color::from('#ff9000'), 'Picker mode replaces the hex row with swatches.');
expectColorInput($grid->cell(0, 2)->bg == $style->background, 'Swatch rows have a blank line between them.');
expectColorInput($grid->cell(4, 3)->glyph === '[' && $grid->cell(5, 3)->bg == Color::from('#bf00bf') && $grid->cell(7, 3)->glyph === ']', 'Brackets surround the selected two-cell swatch.');
$screen->handleEvent(colorInputKey(SDL::KEY_TAB));
expectColorInput(!$widget->paletteMode() && $widget->getValue() === '#bf00bf', 'Tab switches to the hex field with the selected color.');
expectColorInput($widget->preferredHeight() === 2 && $screen->takeLayoutChange(), 'Hex mode requests the compact height.');
$screen->measureGrid(new Tile(0, 0, 20, 8));
expectColorInput($leaf->grid()->height === 2, 'Auto height shrinks after Tab.');
$screen->paint($grid);
expectColorInput($grid->cell(1, 1)->bg == Color::from('#bf00bf'), 'Returning to input mode colors the hex row.');
$screen->handleEvent(colorInputKey(SDL::KEY_RETURN));
expectColorInput($screen->activeLeaf() === null && $widget->getValue() === '#bf00bf', 'Return accepts and deactivates the field.');
$screen->activateLeaf($leaf);
$screen->takeLayoutChange();
$screen->handleEvent(colorInputKey(SDL::KEY_LEFT));
$screen->handleEvent(colorInputKey(SDL::KEY_ESCAPE));
expectColorInput($screen->activeLeaf() === null && $widget->getValue() === '#bf00bf', 'Escape restores the previous color.');
expectColorInput($widget->preferredHeight() === 2 && $screen->takeLayoutChange(), 'Cancelling the palette requests compact height.');
$screen->activateLeaf($leaf);
$screen->takeLayoutChange();
$screen->handleEvent(colorInputKey(SDL::KEY_RIGHT));
$screen->handleEvent(colorInputKey(SDL::KEY_RETURN));
expectColorInput($screen->activeLeaf() === null && !$widget->paletteMode() && $widget->getValue() === '#008c8c', 'Return accepts a palette color and returns to input mode.');
expectColorInput($widget->preferredHeight() === 2 && $screen->takeLayoutChange(), 'Accepting in palette mode requests compact height.');
$screen->activateLeaf($leaf);
$screen->takeLayoutChange();
$screen->handleEvent(colorInputKey(SDL::KEY_TAB));
expectColorInput(!$widget->paletteMode(), 'Tab opens hex mode from the palette.');
$screen->handleEvent(colorInputKey(ord('a'), SDL::MOD_CTRL));
$screen->handleEvent(colorInputText('#123abc'));
expectColorInput($widget->getValue() === '#123abc', 'A complete hex edit updates the value.');
$screen->paint($grid);
expectColorInput($grid->cell(1, 1)->bg == Color::from('#123abc') && $grid->cell(0, 0)->bg == $style->background, 'A complete edit changes only the value row background.');
$screen->handleEvent(colorInputText('z'));
expectColorInput($widget->getValue() === '#123abc', 'Nonhex text is rejected.');
$screen->handleEvent(colorInputKey(SDL::KEY_RETURN));
expectColorInput($widget->getValue() === '#123abc', 'Return accepts an edited hex value.');
foreach ([4 => 4, 9 => 6, 25 => 10, 36 => 12] as $count => $height) {
  $values = array_map(static fn (int $index): string => sprintf('#%06x', $index), range(0, $count - 1));
  $widget->setPalette($values);
  expectColorInput($widget->preferredHeight() === 2, 'The closed palette keeps compact height.');
  $screen->activateLeaf($leaf);
  expectColorInput($widget->preferredHeight() === $height, "{$count} colors use the expected square grid while active.");
  $screen->handleEvent(colorInputKey(SDL::KEY_ESCAPE));
}
$screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
$selectors = $screens[count($screens) - 1];
$selectors->measureGrid(new Tile(0, 0, 100, 30));
$demoInput = $selectors->widget('demoColorInput');
$demoSelector = $selectors->layout->findTile('demoColor')->grid();
$demoTile = $selectors->layout->findTile('demoColorInput')->grid();
expectColorInput($demoInput instanceof ColorInput && $demoInput->preferredHeight() === 2, 'The Selectors demo starts with a compact ColorInput.');
expectColorInput($demoTile->y > $demoSelector->y + $demoSelector->height && $demoTile->height === 2, 'ColorInput sits below ColorSelector with its compact height.');
$selectors->activateLeaf($selectors->layout->findTile('demoColorInput'));
expectColorInput($selectors->takeLayoutChange(), 'Opening demo ColorInput requests remeasurement.');
$selectors->measureGrid(new Tile(0, 0, 100, 30));
$demoTile = $selectors->layout->findTile('demoColorInput')->grid();
expectColorInput($demoInput->paletteMode() && $demoInput->preferredHeight() === 8 && $demoTile->height === 8, 'The Selectors demo grows to show its palette.');
echo "Color input checks passed\n";
