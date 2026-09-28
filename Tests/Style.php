<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Style, Widget};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\Widgets\Button\Button;
use SPTK\Widgets\CheckboxArray\CheckboxArray;
use SPTK\Widgets\Input\Input;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\RadioButton\RadioButton;
use SPTK\Widgets\Text\Text;
use SPTK\Widgets\TextEditor\TextEditor;

/** Fail when a widget paints an unexpected palette color. */
function expectStyleColor(Color $actual, Color $expected, string $name): void {
  if ($actual != $expected) {
    throw new RuntimeException($name . ': unexpected color');
  }
}

/** Paint a widget into a fresh grid for color checks. */
function paintStyleWidget(Widget $widget): Grid {
  $grid = new Grid(20, 2);
  $widget->paint(new GridWriter($grid, new Tile(0, 0, 20, 2)));
  return $grid;
}

$style = new Style(
  background: new Color(10, 20, 30),
  foreground: new Color(40, 50, 60),
  highlight: new Color(70, 80, 90),
  selected: new Color(100, 110, 120),
  cursorBackground: new Color(130, 140, 150),
  cursorForeground: new Color(160, 170, 180),
);
$widgets = [
  new Text('Text', style: $style),
  new Input('Input', style: $style),
  new TextEditor('Editor', style: $style),
  new ListView(['List'], style: $style),
  new RadioButton(['Radio'], style: $style),
  new CheckboxArray(['Checkbox'], style: $style),
];
foreach ($widgets as $widget) {
  $name = get_class($widget);
  expectStyleColor($widget->background(), $style->background, $name . ' background');
  $grid = paintStyleWidget($widget);
  expectStyleColor($grid->cell(0, 0)->bg, $style->background, $name . ' inactive background');
  $normal = $widget instanceof ListView ? $style->selected : $style->foreground;
  expectStyleColor($grid->cell(0, 0)->fg, $normal, $name . ' text');
  $widget->emit('activate');
  $grid = paintStyleWidget($widget);
  expectStyleColor($grid->cell(0, 0)->bg, $style->cursorBackground, $name . ' active cursor');
  $cursor = $widget instanceof Text ? $style->cursorForeground : $normal;
  expectStyleColor($grid->cell(0, 0)->fg, $cursor, $name . ' cursor text');
}
$button = new Button('Button', 'f1', null, $style);
$grid = paintStyleWidget($button);
expectStyleColor($grid->cell(1, 0)->fg, $style->highlight, 'Button hotkey');
expectStyleColor($grid->cell(4, 0)->fg, $style->foreground, 'Button label');
$button->setActivated(true);
$grid = paintStyleWidget($button);
expectStyleColor($grid->cell(4, 0)->fg, $style->background, 'activated Button label');
expectStyleColor($grid->cell(4, 0)->bg, $style->foreground, 'activated Button background');
expectStyleColor($button->background(), $style->background, 'Button tile background');
foreach ([new Input('A'), new TextEditor('A'), new RadioButton(['A']), new CheckboxArray(['A'])] as $widget) {
  expectStyleColor($widget->background(), new Color(0, 0, 0), 'default background');
  expectStyleColor(paintStyleWidget($widget)->cell(0, 0)->fg, new Color(255, 255, 255), 'default foreground');
  $widget->emit('activate');
  expectStyleColor(paintStyleWidget($widget)->cell(0, 0)->bg, new Color(85, 85, 85), 'default cursor');
}
$text = new Text('A');
expectStyleColor($text->background(), new Color(24, 28, 36), 'default Text background');
expectStyleColor(paintStyleWidget($text)->cell(0, 0)->fg, new Color(230, 235, 245), 'default Text foreground');
$list = new ListView(['A']);
expectStyleColor($list->background(), new Color(0, 0, 0), 'default List background');
expectStyleColor(paintStyleWidget($list)->cell(0, 0)->fg, new Color(255, 255, 0), 'default List selected text');
$xml = [
  'Text' => ['<Text>A</Text>', 0],
  'Input' => ['<Input value="A" />', 0],
  'TextEditor' => ['<TextEditor value="A" />', 0],
  'List' => ['<List><Item>A</Item></List>', 0],
  'RadioButton' => ['<RadioButton><Item>A</Item></RadioButton>', 4],
  'CheckboxArray' => ['<CheckboxArray><Item>A</Item></CheckboxArray>', 4],
  'Button' => ['<Button label="A" action="Controller::press" />', 1],
];
foreach ($xml as $name => [$source, $x]) {
  $reader = new XMLReader();
  $reader->XML($source);
  $reader->read();
  $parserClass = 'SPTK\\Widgets\\' . $name . '\\Parser';
  $widget = (new $parserClass())->parse($reader, $style)->widget;
  expectStyleColor($widget->background(), $style->background, $name . ' XML background');
  $foreground = $name === 'List' ? $style->selected : $style->foreground;
  expectStyleColor(paintStyleWidget($widget)->cell($x, 0)->fg, $foreground, $name . ' XML foreground');
  $reader->close();
}
expectStyleColor($style->cursorForeground, new Color(160, 170, 180), 'shared palette remains unchanged');
echo "Style checks passed\n";
