<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Events\EventContext;
use SPTK\Layout\{LayoutLeaf, Tile};
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Slider\{Parser, Slider};

/** Count changes caused by a user's slider keys. */
function sliderChanged(): void {
  global $changes;
  $changes++;
}

/** Build one SDL key event for the slider. */
function sliderKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0]];
}

/** Receive a parsed slider event through its layout leaf. */
final class SliderAction {
  public static ?EventContext $last = null;

  public static function changed(EventContext $event): void {
    self::$last = $event;
  }

  public static function toggle(EventContext $event): bool {
    $event->widget->setMuted(!$event->widget->isMuted());
    return true;
  }
}

$changes = 0;
$default = new Slider(50);
$default->handleInput(sliderKey(SDL::KEY_RIGHT));
if ($default->getValue() !== 51.0) {
  throw new RuntimeException('Right must use the default small step of one.');
}
$default->handleInput(sliderKey(SDL::KEY_UP));
if ($default->getValue() !== 61.0) {
  throw new RuntimeException('Up must use the default normal step of ten.');
}
$default->handleInput(sliderKey(SDL::KEY_DOWN));
$default->handleInput(sliderKey(SDL::KEY_LEFT));
if ($default->getValue() !== 50.0) {
  throw new RuntimeException('Down and Left must use the normal and small steps respectively.');
}
$default->handleInput(sliderKey(SDL::KEY_HOME));
$default->handleInput(sliderKey(SDL::KEY_END));
if ($default->getValue() !== 100.0) {
  throw new RuntimeException('Home and End must select the range endpoints.');
}
$slider = new Slider(50, 0, 100, 5, 'MASTER');
$slider->on('change', 'sliderChanged');
$slider->setValue(61);
if ($slider->getValue() !== 61.0 || $changes !== 0 || $slider->preferredHeight() !== 1) {
  throw new RuntimeException('Programmatic slider values must not emit user changes.');
}
$slider->handleInput(sliderKey(SDL::KEY_RIGHT));
$slider->handleInput(sliderKey(SDL::KEY_END));
$slider->handleInput(sliderKey(SDL::KEY_END));
$slider->handleInput(sliderKey(SDL::KEY_HOME));
if ($slider->getValue() !== 0.0 || $changes !== 3) {
  throw new RuntimeException('Arrow and endpoint keys must change a slider only when its value moves.');
}
$slider->setValue(50);
$grid = new Grid(30, 1);
$slider->paint(new GridWriter($grid, new Tile(0, 0, 30, 1)));
if ($grid->cell(0, 0)->glyph !== 'M' || $grid->cell(0, 0)->fg != (new Style())->foreground
  || $grid->cell(7, 0)->bg != (new Style())->selected
  || $grid->cell(18, 0)->bg != (new Style())->background->darkened(0.85)
  || $grid->cell(29, 0)->glyph !== '%') {
  throw new RuntimeException('Slider must paint its label, filled cells, darker empty track, and numeric value.');
}
$slider->setTitle('SOUND');
if ($slider->preferredHeight() !== 2) {
  throw new RuntimeException('A titled slider must reserve a heading row.');
}
$grid = new Grid(30, 2);
$slider->paint(new GridWriter($grid, new Tile(0, 0, 30, 2)));
if ($grid->cell(0, 0)->fg != (new Style())->highlight) {
  throw new RuntimeException('An unmuted slider title must keep the highlight color.');
}
$slider->setMuted(true);
$slider->paint(new GridWriter($grid, new Tile(0, 0, 30, 2)));
if ($grid->cell(0, 0)->glyph !== 'S' || $grid->cell(0, 0)->fg != new Color(150, 150, 150)
  || $grid->cell(0, 1)->glyph !== 'M' || $grid->cell(0, 1)->fg != new Color(150, 150, 150)) {
  throw new RuntimeException('Muted slider text must use gray while retaining its title row.');
}
$gray = new Color(150, 150, 150);
if ($grid->cell(0, 0)->fg != $gray || $grid->cell(7, 1)->bg != $gray
  || $grid->cell(29, 1)->fg != $gray || $slider->getValue() !== 50.0 || $changes !== 3) {
  throw new RuntimeException('Mute must gray the title, filled bar, and value without changing volume.');
}
$slider->setMuted(false);
$slider->setTitle(null);
if ($slider->preferredHeight() !== 1) {
  throw new RuntimeException('Removing a slider title must restore its one-row height.');
}
$reader = new XMLReader();
$reader->XML('<Slider value="30" max="100" step="5" smallStep="2" title="DISPLAY" label="LIGHT" muted="true"><Event type="change" action="SliderAction::changed" /><Event type="keyDown" key="m" action="SliderAction::toggle" /></Slider>');
$reader->read();
$parser = new Parser();
$parser->validateAttributes($reader, []);
$definition = $parser->parse($reader, new Style());
if (!$definition->widget instanceof Slider || $definition->widget->getValue() !== 30.0
  || $definition->widget->preferredHeight() !== 2 || !$definition->widget->isMuted() || count($definition->events) !== 2) {
  throw new RuntimeException('Slider XML must parse its value, title, mute state, and actions.');
}
$leaf = new LayoutLeaf('Slider', '', '', $definition->widget, $definition->events);
if ($leaf->handleEvent(sliderKey(ord('m'))) || !$leaf->dispatchInput('keyDown', sliderKey(ord('m')))
  || $definition->widget->isMuted() || $definition->widget->getValue() !== 30.0) {
  throw new RuntimeException('An active slider may use a local M action to toggle mute without changing value.');
}
$leaf->handleEvent(sliderKey(SDL::KEY_RIGHT));
if ($definition->widget->getValue() !== 32.0) {
  throw new RuntimeException('Slider XML must configure the small step.');
}
$leaf->handleEvent(sliderKey(SDL::KEY_UP));
if (SliderAction::$last?->widget !== $definition->widget || $definition->widget->getValue() !== 37.0) {
  throw new RuntimeException('Slider arrow input must reach the XML change action.');
}

echo "Slider checks passed\n";
