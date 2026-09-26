<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen};
use SPTK\Events\{EventContext, EventDefinition, KeyboardEvent};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\CheckboxArray\CheckboxArray;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\RadioButton\RadioButton;

/** Count widget-originated change notifications in headless checks. */
final class ChoiceTestListener {

  public static int $changes = 0;
  public static int $xmlChanges = 0;

  /** Count one changed value. */
  public static function change(): void {
    self::$changes++;
  }

  /** Count one XML-dispatched change notification. */
  public static function xmlChange(EventContext $event): void {
    self::$xmlChanges++;
  }

}

/** Assert a named expected result. */
function expectChoice(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Create an SDL-shaped key press. */
function choiceKey(int $key, int $mod = 0, bool $repeat = false): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod, 'repeat' => $repeat]];
}

/** Create an SDL-shaped text input event. */
function choiceText(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

/** Put a widget in one measurable screen tile. */
function choiceScreen(RadioButton|CheckboxArray|ListView $widget, int $height = 3): Screen {
  $layout = new LayoutNode('vertical', '1*', '1*');
  $layout->addLeaf(new LayoutLeaf('Choice', '', '', $widget));
  $screen = new Screen($layout);
  $screen->measureGrid(new Tile(0, 0, 20, $height));
  return $screen;
}

/** Confirm invalid setters leave a value intact. */
function expectChoiceError(RadioButton|CheckboxArray|ListView $widget, string $name): void {
  try {
    $widget->setValue($widget instanceof CheckboxArray ? ['missing'] : 'missing');
  } catch (InvalidArgumentException $error) {
    return;
  }
  throw new RuntimeException($name . ': expected InvalidArgumentException');
}

$parser = new SPTK\XmlParser\XmlParser();
expectChoice(count($parser->windows[0]['screens']), 5, 'demo screen count');
foreach ($parser->windows[0]['screens'] as $screen) {
  $screen->measureGrid(new Tile(0, 0, 80, 12));
  $screen->paint(new Grid(80, 12));
}
$choiceLeaves = $parser->windows[0]['screens'][2]->layout->leaves();
expectChoice($choiceLeaves[1]->instance() instanceof RadioButton, true, 'RadioButton XML');
expectChoice($choiceLeaves[2]->instance() instanceof CheckboxArray, true, 'CheckboxArray XML');
$listLeaves = $parser->windows[0]['screens'][3]->layout->leaves();
expectChoice($listLeaves[1]->instance() instanceof ListView, true, 'single List XML');
expectChoice($listLeaves[2]->instance() instanceof ListView, true, 'multiple List XML');
$radio = new RadioButton(['alpha', 'beta', 'gamma']);
expectChoice($radio->getValue(), 'alpha', 'radio default');
$radio->on('change', [ChoiceTestListener::class, 'change']);
$screen = choiceScreen($radio, 2);
$screen->handleEvent(choiceKey(SDL::KEY_RETURN));
expectChoice($radio->active(), true, 'radio active');
$screen->handleEvent(choiceKey(SDL::KEY_DOWN));
$screen->handleEvent(choiceKey(SDL::KEY_SPACE));
expectChoice($radio->getValue(), 'beta', 'radio selected');
expectChoice(ChoiceTestListener::$changes, 1, 'radio change once');
$screen->handleEvent(choiceKey(SDL::KEY_SPACE, repeat: true));
expectChoice(ChoiceTestListener::$changes, 1, 'radio suppress repeat');
$radio->setValue('gamma');
expectChoice(ChoiceTestListener::$changes, 1, 'radio setter silent');
expectChoiceError($radio, 'radio unknown');
expectChoice($radio->getValue(), 'gamma', 'radio setter atomic');
$screen->handleEvent(choiceKey(SDL::KEY_ESCAPE));
expectChoice($radio->active(), false, 'radio escape release');
$boxes = new CheckboxArray([['value' => 'a', 'checked' => true], 'b', 'c']);
expectChoice($boxes->getValue(), ['a'], 'checkbox initial');
$boxes->on('change', [ChoiceTestListener::class, 'change']);
$screen = choiceScreen($boxes);
$screen->handleEvent(choiceKey(SDL::KEY_RETURN));
$screen->handleEvent(choiceKey(SDL::KEY_DOWN));
$screen->handleEvent(choiceKey(SDL::KEY_SPACE));
expectChoice($boxes->getValue(), ['a', 'b'], 'checkbox toggle');
expectChoice(ChoiceTestListener::$changes, 2, 'checkbox change');
$boxes->setValue(['c']);
expectChoice($boxes->getValue(), ['c'], 'checkbox setter');
expectChoiceError($boxes, 'checkbox unknown');
expectChoice($boxes->getValue(), ['c'], 'checkbox setter atomic');
$nativeFfi = FFI::cdef(file_get_contents(APP_DIR . '/SPTK/SDLWrapper/sdl_extract.h'), APP_DIR . '/SPTK/SDLWrapper/libSDL3.so.0.2.21');
$nativeSpace = $nativeFfi->new('SDL_Event');
$nativeSpace->type = SDL::SDL_EVENT_KEY_DOWN;
$nativeSpace->key->key = SDL::KEY_SPACE;
$nativeSpace->key->mod = 0;
$nativeSpace->key->repeat = false;
$keyboardSpace = new KeyboardEvent($nativeSpace);
expectChoice(is_bool($keyboardSpace->key->repeat), true, 'keyboard repeat is a PHP boolean');
$screen->handleEvent($keyboardSpace);
expectChoice($boxes->getValue(), ['b', 'c'], 'native SDL Space toggles checkbox');
expectChoice(ChoiceTestListener::$changes, 3, 'native SDL Space emits change');
$nativeSpace->key->repeat = true;
$screen->handleEvent(new KeyboardEvent($nativeSpace));
expectChoice($boxes->getValue(), ['b', 'c'], 'native repeat does not toggle checkbox');
ChoiceTestListener::$changes = 2;
$grid = new Grid(8, 2);
$radio->paint(new GridWriter($grid, new Tile(0, 0, 8, 2)));
expectChoice($grid->cell(7, 0)->glyph, '▲', 'radio scroll arrow right');
expectChoice([$grid->cell(7, 0)->fg->r, $grid->cell(7, 0)->bg->g], [0, 255], 'radio inverted scroll colors');
$list = new ListView(['alpha', 'beta', 'gamma'], reorderable: true);
expectChoice($list->getValue(), 'alpha', 'list initial value');
$list->on('change', [ChoiceTestListener::class, 'change']);
$screen = choiceScreen($list, 2);
$screen->handleEvent(choiceKey(SDL::KEY_RETURN));
$screen->handleEvent(choiceKey(SDL::KEY_DOWN));
expectChoice($list->getValue(), 'beta', 'list cursor value');
expectChoice(ChoiceTestListener::$changes, 3, 'list cursor change');
$screen->handleEvent(choiceText('g'));
expectChoice($list->getValue(), 'gamma', 'list prefix filter');
expectChoice($list->filter(), 'g', 'list query');
expectChoice(ChoiceTestListener::$changes, 4, 'list query value change');
$screen->handleEvent(choiceKey(SDL::KEY_BACKSPACE));
expectChoice($list->filter(), '', 'list backspace');
$list->setValue('beta');
expectChoice(ChoiceTestListener::$changes, 4, 'list setter silent');
$list->setFilter('missing');
expectChoice($list->getValue(), null, 'single list no results');
$list->setFilter('');
expectChoice($list->getValue(), 'beta', 'single list restores cursor after no results');
$screen->handleEvent(choiceKey(SDL::KEY_DOWN, SDL::MOD_SHIFT));
expectChoice($list->values(), ['alpha', 'gamma', 'beta'], 'list reorder');
expectChoice($list->getValue(), 'beta', 'reorder preserves identity');
expectChoice(ChoiceTestListener::$changes, 4, 'reorder value unchanged');
$screen->handleEvent(choiceKey(SDL::KEY_ESCAPE));
$multi = new ListView([['value' => 'a', 'selected' => true], 'b', 'c'], multiple: true);
$multi->on('change', [ChoiceTestListener::class, 'change']);
$screen = choiceScreen($multi);
$screen->handleEvent(choiceKey(SDL::KEY_RETURN));
$screen->handleEvent(choiceKey(SDL::KEY_DOWN));
expectChoice(ChoiceTestListener::$changes, 4, 'multiple cursor does not change selection');
$screen->handleEvent(choiceKey(SDL::KEY_SPACE));
expectChoice($multi->getValue(), ['a', 'b'], 'multiple list toggle');
expectChoice(ChoiceTestListener::$changes, 5, 'multiple list change');
$screen->handleEvent(choiceKey(SDL::KEY_SPACE, repeat: true));
expectChoice(ChoiceTestListener::$changes, 5, 'multiple list repeat');
$multi->setFilter('zzz');
expectChoice($multi->activeValue(), null, 'no results cursor');
expectChoice($multi->getValue(), ['a', 'b'], 'multiple selection survives filter');
$grid = new Grid(24, 2);
$multi->paint(new GridWriter($grid, new Tile(0, 0, 24, 2)));
expectChoice($grid->cell(0, 0)->glyph, '(', 'no results message');
$multi->setFilter('');
$nativeSpace->key->repeat = false;
$screen->handleEvent(new KeyboardEvent($nativeSpace));
expectChoice($multi->getValue(), ['a'], 'native SDL Space toggles list');
$nativeSpace->key->repeat = true;
$screen->handleEvent(new KeyboardEvent($nativeSpace));
expectChoice($multi->getValue(), ['a'], 'native repeat does not toggle list');
$search = new ListView(['alpha', 'beta', 'gamma'], filterable: false, searchable: true);
$search->setFilter('g');
expectChoice($search->getValue(), 'gamma', 'search-only moves to first match');
expectChoice($search->values(), ['alpha', 'beta', 'gamma'], 'search-only keeps all rows');
$xmlRadio = new RadioButton(['one', 'two']);
$xmlEvents = [new EventDefinition('change', null, ChoiceTestListener::class . '::xmlChange')];
$xmlLayout = new LayoutNode('horizontal', '1*', '1*');
$xmlLayout->addLeaf(new LayoutLeaf('RadioButton', '1*', '', $xmlRadio, $xmlEvents));
$neighbor = new CheckboxArray(['x']);
$xmlLayout->addLeaf(new LayoutLeaf('CheckboxArray', '1*', '', $neighbor));
$xmlScreen = new Screen($xmlLayout, events: $xmlEvents);
$xmlScreen->measureGrid(new Tile(0, 0, 40, 3));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_RETURN));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_DOWN));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_SPACE));
expectChoice(ChoiceTestListener::$xmlChanges, 2, 'widget and screen XML change forwarded once each');
$xmlScreen->handleEvent(choiceKey(SDL::KEY_RIGHT));
expectChoice($xmlRadio->active(), true, 'right keeps choice active');
$xmlScreen->handleEvent(choiceKey(SDL::KEY_UP));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_SPACE));
expectChoice($xmlRadio->getValue(), 'one', 'Space still changes choice after Right');
$xmlScreen->handleEvent(choiceKey(SDL::KEY_ESCAPE));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_RIGHT));
$xmlScreen->handleEvent(choiceKey(SDL::KEY_RETURN));
expectChoice($neighbor->active(), true, 'right selects neighbor after release');
echo "Choice and list checks passed\n";
