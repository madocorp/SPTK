<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\Widgets\Title\{Parser, Title};

/** Assert one title property or rendered cell. */
function expectTitle(mixed $actual, mixed $expected, string $name): void {
  if ($actual != $expected) {
    throw new RuntimeException($name . ': unexpected result');
  }
}

/** Parse a standalone Title element with the inherited style. */
function parseTitle(string $xml, Style $style): Title {
  $reader = new XMLReader();
  $reader->XML($xml);
  $reader->read();
  $parser = new Parser();
  $parser->validateAttributes($reader, []);
  $title = $parser->parse($reader, $style)->widget;
  $reader->close();
  return $title;
}

$style = new Style(background: new Color(4, 5, 6), foreground: new Color(7, 8, 9), highlight: new Color(10, 11, 12));
$title = parseTitle('<Title>Éclair</Title>', $style);
expectTitle($title->preferredHeight(), 1, 'one-row height');
expectTitle($title->canActivate(), false, 'display-only title');
expectTitle($title->tip(), 'Screen title; arrow keys move between tiles.', 'title tip');
$grid = new Grid(10, 2);
$title->paint(new GridWriter($grid, new Tile(0, 0, 10, 2)));
expectTitle($grid->cell(0, 0)->glyph, 'É', 'heading text');
expectTitle($grid->cell(0, 0)->fg, $style->highlight, 'highlight ink');
expectTitle($grid->cell(0, 0)->bg, $style->background, 'inherited background');
expectTitle($grid->cell(9, 1)->bg, $style->background, 'unused rows are cleared');
$title->setTips('Heading help');
expectTitle($title->tip(), 'Heading help', 'tip override');
$changes = 0;
$title->on('change', function () use (&$changes): void {
  $changes++;
});
$title->setText('Longer heading');
expectTitle([$title->text(), $changes], ['Longer heading', 1], 'dynamic heading');
$title->paint(new GridWriter($grid, new Tile(0, 0, 10, 1)));
expectTitle($grid->cell(9, 0)->glyph, 'a', 'long heading is clipped');
$styled = parseTitle('<Title><Style><Highlight>#abcdef</Highlight></Style>Local</Title>', $style);
$styledGrid = new Grid(5, 1);
$styled->paint(new GridWriter($styledGrid, new Tile(0, 0, 5, 1)));
expectTitle($styledGrid->cell(0, 0)->fg, new Color(171, 205, 239), 'local highlight override');
foreach (['<Title>First' . "\n" . 'second</Title>', '<Title><Text>Nested</Text></Title>', '<Title wrap="true">Bad</Title>'] as $xml) {
  try {
    parseTitle($xml, $style);
    throw new LogicException('Invalid Title accepted: ' . $xml);
  } catch (InvalidArgumentException|RuntimeException $error) {
  }
}
echo "Title checks passed\n";
