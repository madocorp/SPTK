<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\ColorSelector\ColorSelector;

/** Check one color selector result. */
function expectColor(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Build a key event using the current SDL input shape. */
function colorKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false]];
}

/** Build a text event with an FFI character buffer. */
function colorText(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

/** Count color changes emitted by user input. */
function colorChanged(): void {
  global $changes;
  $changes++;
}

$style = new Style();
$selector = new ColorSelector(style: $style);
$changes = 0;
$selector->on('change', colorChanged(...));
expectColor([$selector->getValue(), $selector->cursorPosition(), $selector->preferredWidth(), $selector->preferredHeight()], ['#ff0000', 32, 64, 7], 'default palette');
$grid = new Grid(64, 7);
$selector->paint(new GridWriter($grid, new Tile(0, 0, 64, 7)));
expectColor([$grid->cell(0, 4)->glyph, $grid->cell(3, 4)->glyph], ['[', ']'], 'exact red cursor');
expectColor($grid->cell(11, 6)->bg == Color::from('#ff0000'), true, 'preview stripe');
expectColor($grid->cell(5, 0)->bg == Color::from('#e61717'), true, 'rainbow swatch');
expectColor($grid->cell(1, 0)->bg == Color::from('#000000'), true, 'black swatch is painted');
$selector->emit('activate');
$selector->handleInput(colorKey(SDL::KEY_PAGEUP));
$selector->handleInput(colorKey(SDL::KEY_RIGHT));
expectColor([$selector->cursorPosition(), $selector->getValue()], [1, '#e61717'], 'rainbow navigation');
$selector->handleInput(colorKey(SDL::KEY_KP_2));
expectColor($selector->cursorPosition(), 23, 'keypad down chooses matching tone');
$selector->handleInput(colorKey(SDL::KEY_RIGHT));
expectColor($selector->getValue(), '#e82d2d', 'tone navigation');
$selector->handleInput(colorKey(SDL::KEY_HOME));
$selector->handleInput(colorKey(SDL::KEY_PAGEUP));
$selector->handleInput(colorKey(SDL::KEY_DOWN));
$selector->handleInput(colorKey(SDL::KEY_END));
expectColor($selector->getValue(), '#ffffff', 'grayscale from black');
$before = $changes;
$selector->handleInput(colorText('A'));
expectColor([$selector->hexText(), $selector->getValue(), $changes], ['#a', '#ffffff', $before], 'incomplete hex does not commit');
$selector->handleInput(colorText('xyz'));
expectColor($selector->hexText(), '#a', 'nonhex text ignored');
$selector->handleInput(colorText('B12'));
$selector->handleInput(colorKey(SDL::KEY_BACKSPACE));
$selector->handleInput(colorText('234'));
expectColor([$selector->getValue(), $selector->cursorPosition(), $changes], ['#ab1234', 47, $before + 1], 'corrected hex commits once');
$selector->paint(new GridWriter($grid, new Tile(0, 0, 64, 7)));
expectColor($grid->cell(61, 4)->bg == Color::from('#ab1234'), true, 'custom swatch');
expectColor($grid->cell(63, 6)->bg == Color::from('#ab1234'), true, 'custom preview');
$selector->handleInput(colorKey(SDL::KEY_LEFT));
$selector->handleInput(colorKey(SDL::KEY_RIGHT));
expectColor($selector->getValue(), '#ab1234', 'custom swatch persists');
$selector->handleInput(colorText('f'));
$selector->emit('deactivate');
expectColor([$selector->active(), $selector->hexText(), $selector->getValue()], [false, '#ab1234', '#ab1234'], 'release discards incomplete text');
$selector->emit('activate');
$selector->handleInput(colorText('123456'));
$selector->handleInput(colorText('0'));
expectColor($selector->hexText(), '#0', 'new typing starts fresh');
$selector->handleInput(colorKey(SDL::KEY_DELETE));
expectColor($selector->hexText(), '#', 'Delete clears hex field');
$selector->setValue('ABCDEF');
expectColor($selector->getValue(), '#abcdef', 'setter normalizes hex');
$before = $changes;
try {
  $selector->setValue('#bad');
  throw new RuntimeException('invalid color accepted');
} catch (InvalidArgumentException $error) {
  expectColor([$selector->getValue(), $changes], ['#abcdef', $before], 'invalid setter preserves state');
}
$narrow = new Grid(10, 4);
$selector->paint(new GridWriter($narrow, new Tile(0, 0, 10, 4)));
expectColor($narrow->cell(5, 0)->bg == Color::from('#e61717'), true, 'narrow palette clips safely');
$reader = new XMLReader();
$reader->XML('<ColorSelector value="#12AB34"><Style><Background>#202020</Background></Style><Event type="change" action="Controller::colorChanged" /></ColorSelector>');
$reader->read();
$parser = new SPTK\Widgets\ColorSelector\Parser();
$parser->validateAttributes($reader, []);
$definition = $parser->parse($reader, $style);
expectColor([$definition->widget->getValue(), $definition->widget->background() == Color::from('#202020'), count($definition->events)], ['#12ab34', true, 1], 'XML value style and event');
foreach (['<ColorSelector value="#bad" />', '<ColorSelector>body</ColorSelector>', '<ColorSelector><![CDATA[body]]></ColorSelector>', '<ColorSelector><Text /></ColorSelector>'] as $invalid) {
  $reader = new XMLReader();
  $reader->XML($invalid);
  $reader->read();
  try {
    $parser->parse($reader, $style);
    throw new RuntimeException('invalid ColorSelector XML accepted');
  } catch (InvalidArgumentException|RuntimeException $error) {
    expectColor($error->getMessage() !== 'invalid ColorSelector XML accepted', true, 'invalid XML rejected');
  }
}
$screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
$colors = $screens[count($screens) - 1]->widget('demoColor');
expectColor($colors instanceof ColorSelector, true, 'demo screen parses selector');
echo "Color selector checks passed\n";
