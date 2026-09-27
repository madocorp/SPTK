<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\ColorSelector\ColorSelector;
use SPTK\Widgets\DateSelector\DateSelector;

/** Check one calendar result. */
function expectDate(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Build a key event using the current SDL input shape. */
function dateKey(int $key, int $modifiers = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $modifiers, 'repeat' => false]];
}

/** Build a text event with an FFI character buffer. */
function dateInput(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

/** Count date changes caused by user input. */
function dateChanged(): void {
  global $dateChanges;
  $dateChanges++;
}

/** Return one painted calendar row as text. */
function dateRow(Grid $grid, int $y): string {
  $row = '';
  for ($x = 0; $x < $grid->width(); $x++) {
    $row .= $grid->cell($x, $y)->glyph;
  }
  return $row;
}

$style = new Style();
$widget = new DateSelector('2024-01-31', $style);
$dateChanges = 0;
$widget->on('change', dateChanged(...));
expectDate([$widget->getValue(), $widget->preferredWidth(), $widget->preferredHeight()], ['2024-01-31', 28, 9], 'initial date and size');
$widget->emit('activate');
$widget->handleInput(dateKey(SDL::KEY_PAGEDOWN));
expectDate([$widget->getValue(), $dateChanges], ['2024-02-29', 1], 'month page clamps leap day and emits change');
$widget->handleInput(dateKey(SDL::KEY_PAGEDOWN, SDL::MOD_SHIFT));
expectDate($widget->getValue(), '2025-02-28', 'year page clamps leap day');
$widget->handleInput(dateKey(SDL::KEY_PAGEUP, SDL::MOD_SHIFT));
expectDate($widget->getValue(), '2024-02-28', 'previous year retains day');
$widget->handleInput(dateKey(SDL::KEY_END));
expectDate($widget->getValue(), '2024-02-29', 'End reaches month end');
$before = $dateChanges;
$widget->handleInput(dateKey(SDL::KEY_RIGHT));
expectDate([$widget->getValue(), $dateChanges], ['2024-02-29', $before], 'arrow cannot cross month boundary');
$widget->handleInput(dateKey(SDL::KEY_HOME));
$widget->handleInput(dateKey(SDL::KEY_KP_8));
expectDate($widget->getValue(), '2024-02-01', 'keypad Up stops at month start');
$widget->handleInput(dateKey(SDL::KEY_DOWN));
expectDate($widget->getValue(), '2024-02-08', 'Down advances one week');
$widget->handleInput(dateKey(SDL::KEY_PAGEUP));
expectDate($widget->getValue(), '2024-01-08', 'Page Up changes month');
$widget->setValue('2024-02-29');
$before = $dateChanges;
$widget->handleInput(dateInput('2'));
expectDate([$widget->dateText(), $widget->getValue(), $dateChanges], ['2___-__-__', '2024-02-29', $before], 'incomplete draft leaves date unchanged');
$widget->handleInput(dateInput('0230229'));
expectDate([$widget->dateText(), $widget->getValue()], ['2023-02-29', '2024-02-29'], 'invalid leap date stays editable');
$widget->handleInput(dateKey(SDL::KEY_BACKSPACE));
$widget->handleInput(dateInput('8'));
expectDate([$widget->getValue(), $dateChanges], ['2023-02-28', $before + 1], 'corrected draft commits once');
$widget->handleInput(dateInput('20241231'));
expectDate($widget->getValue(), '2024-12-31', 'new typing replaces complete draft');
$widget->handleInput(dateKey(SDL::KEY_DELETE));
expectDate($widget->dateText(), '____-__-__', 'Delete clears draft');
$widget->handleInput(dateInput('x- '));
expectDate($widget->dateText(), '____-__-__', 'non-digit input ignored');
$widget->handleInput(dateInput('1999'));
$widget->emit('deactivate');
expectDate([$widget->active(), $widget->dateText(), $widget->getValue()], [false, '2024-12-31', '2024-12-31'], 'release discards incomplete date');
$widget->emit('activate');
$widget->handleInput(dateKey(SDL::KEY_SPACE));
expectDate($widget->getValue(), date('Y-m-d'), 'Space selects today');
$before = $dateChanges;
$widget->setValue('2000-02-29');
expectDate($dateChanges, $before, 'programmatic setter has no change event');
foreach (['1900-02-29', '2024-04-31', '2024-13-01', '0000-01-01', '10000-01-01', '2024-2-01', "2024-02-01\n", ''] as $invalid) {
  try {
    $widget->setValue($invalid);
    throw new RuntimeException('invalid date accepted');
  } catch (InvalidArgumentException $error) {
    expectDate($widget->getValue(), '2000-02-29', 'invalid setter preserves date');
  }
}
foreach ([['0001-01-01', SDL::KEY_LEFT], ['0001-01-01', SDL::KEY_PAGEUP], ['9999-12-31', SDL::KEY_RIGHT], ['9999-12-31', SDL::KEY_PAGEDOWN]] as [$value, $key]) {
  $widget->setValue($value);
  $widget->handleInput(dateKey($key));
  expectDate($widget->getValue(), $value, 'navigation stays within supported years');
}
$widget->setValue('2024-09-30');
$grid = new Grid(28, 9);
$widget->paint(new GridWriter($grid, new Tile(0, 0, 28, 9)));
expectDate(trim(dateRow($grid, 0)), 'September 2024', 'month heading');
expectDate(trim(dateRow($grid, 1)), 'Mon Tue Wed Thu Fri Sat Sun', 'Monday-first weekdays');
expectDate(substr(dateRow($grid, 2), 24, 4), '  1 ', 'first day aligned to Sunday');
expectDate(substr(dateRow($grid, 7), 0, 4), ' 30 ', 'sixth week is visible');
expectDate($grid->cell(1, 7)->bg == $style->cursorBackground, true, 'selected date cursor');
expectDate(trim(dateRow($grid, 8)), '2024-09-30', 'date footer');
expectDate($grid->cell(1, 2)->fg != $style->foreground, true, 'adjacent month is dimmed');
$narrow = new Grid(10, 3);
$widget->paint(new GridWriter($narrow, new Tile(0, 0, 10, 3)));
expectDate($narrow->cell(0, 1)->glyph, 'M', 'small tiles clip safely');
$reader = new XMLReader();
$reader->XML('<DateSelector value="2024-02-29"><Style><Background>#202020</Background></Style><Event type="change" action="Controller::dateChanged" /></DateSelector>');
$reader->read();
$parser = new SPTK\Widgets\DateSelector\Parser();
$parser->validateAttributes($reader, []);
$definition = $parser->parse($reader, $style);
expectDate([$definition->widget->getValue(), $definition->widget->background() == Color::from('#202020'), count($definition->events)], ['2024-02-29', true, 1], 'XML value style and event');
foreach (['<DateSelector value="2023-02-29" />', '<DateSelector>body</DateSelector>', '<DateSelector><![CDATA[body]]></DateSelector>', '<DateSelector><Text /></DateSelector>'] as $invalid) {
  $reader = new XMLReader();
  $reader->XML($invalid);
  $reader->read();
  try {
    $parser->parse($reader, $style);
    throw new RuntimeException('invalid DateSelector XML accepted');
  } catch (InvalidArgumentException|RuntimeException $error) {
    expectDate($error->getMessage() !== 'invalid DateSelector XML accepted', true, 'invalid XML rejected');
  }
}
$screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
$demo = $screens[count($screens) - 1];
expectDate($demo->widget('demoDate') instanceof DateSelector, true, 'demo screen contains DateSelector');
expectDate($demo->widget('demoColor') instanceof ColorSelector, true, 'demo screen retains ColorSelector');
$demo->measureGrid(new Tile(0, 0, 98, 29));
$demo->paint(new Grid(98, 29));
$colorTile = null;
$dateTile = null;
foreach ($demo->layout->leaves() as $leaf) {
  if ($leaf->instance()->id() === 'demoColor') {
    $colorTile = $leaf->grid();
  } else if ($leaf->instance()->id() === 'demoDate') {
    $dateTile = $leaf->grid();
  }
}
expectDate($colorTile !== null && $dateTile !== null && $dateTile->x > $colorTile->x + $colorTile->width && $dateTile->y === $colorTile->y && $dateTile->height >= 9, true, 'both selectors fit side by side');
echo "Date selector checks passed\n";
