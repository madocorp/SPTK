<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, PixelBox, PixelSplitter, Tile, WindowGeometry};
use SPTK\Widgets\Empty\Placeholder;
use SPTK\Widgets\StyledText\StyledText;
use SPTK\Widgets\Text\Text;
use SPTK\XmlParser\{ScreenParser, StyleParser};

function expectPixel(mixed $actual, mixed $expected, string $message): void {
  if ($actual !== $expected) {
    throw new RuntimeException($message . ': ' . var_export($actual, true));
  }
}

$parentColor = Color::from('#102030');
$ownColor = Color::from('#405060');
$borderColor = Color::from('#708090');
$root = new LayoutNode('horizontal', '1*', '1*');
$root->addLeaf(new LayoutLeaf('Empty', '2', '1*', new Placeholder($parentColor)));
$pixel = new LayoutNode('vertical', '1*', '1*', pixelMode: true, box: new PixelBox(
  margin: 3, borderWidth: 2, padding: 4, background: $ownColor, borderColor: $borderColor,
));
$first = new LayoutLeaf('StyledText', '1*', 'auto', new StyledText('A short line'), box: new PixelBox(background: $ownColor));
$second = new LayoutLeaf('Empty', '1*', '0*', new Placeholder($ownColor));
$pixel->addLeaf($first);
$pixel->addLeaf($second);
$root->addNode($pixel);
$grid = new Tile(0, 0, 20, 8);
$geometry = new WindowGeometry(8, 16, 160, 128, 0, 0);
$root->measureGrid($grid);
$root->measureArea($grid, $geometry);
$outer = $geometry->backgroundArea($pixel->grid(), $grid);
$inner = $pixel->pixelContent();
expectPixel($inner->x, $outer->x + 9, 'Margin, border, and padding inset the left edge');
expectPixel($inner->y, $outer->y + 9, 'Margin, border, and padding inset the top edge');
expectPixel($inner->width, $outer->width - 18, 'Both horizontal edges are included');
expectPixel($first->pixelTile()->y, $inner->y, 'First child starts at inner edge');
expectPixel($second->pixelTile()->y, $first->pixelTile()->y + $first->pixelTile()->height, 'No automatic pixel gap');
expectPixel($second->pixelTile()->y + $second->pixelTile()->height, $inner->y + $inner->height, 'Zero intrinsic filler takes remaining height');
expectPixel($first->instance()->contentHeight($first->pixelContent()->width), $first->pixelContent()->height, 'Text auto height is exact in pixels');
expectPixel($root->leaves()[0]->isPixel(), false, 'Grid sibling stays on the grid');

$areas = PixelSplitter::split(new Tile(0, 0, 101, 20), 'horizontal', ['25%', '1*', '1*'], 101, 20);
expectPixel(array_sum(array_map(fn(Tile $area): int => $area->width, $areas)), 101, 'Pixel splitter allocates every pixel');
expectPixel($areas[1]->x, $areas[0]->width, 'Split rectangles are adjacent');

$screen = (new ScreenParser())->parse('pixel.xml', new Style(), 'pixel', 'Pixel');
$screen->measureGrid(new Tile(0, 0, 30, 10));
$screen->measureArea(new Tile(0, 0, 30, 10), new WindowGeometry(8, 16, 240, 160, 0, 0));
$leaves = $screen->layout->leaves();
expectPixel(count($leaves), 2, 'XML pixel layout builds a text and filler leaf');
expectPixel($leaves[0]->isPixel(), true, 'XML pixel mode reaches descendants');
expectPixel($leaves[0]->pixelTile()->x, 10, 'A leading Style sets the layout node margin, border, and padding');
expectPixel($leaves[0]->pixelContent()->x > $leaves[0]->pixelTile()->x, true, 'Local Style margin and padding apply to a widget leaf');
expectPixel($leaves[1]->pixelContent()->x, $leaves[1]->pixelTile()->x, 'Widget box edges do not inherit to a sibling');
$styleReader = new XMLReader();
$styleReader->XML('<Style><BorderColor>#112233</BorderColor><Margin>1px 2px</Margin><BorderWidth>2px 3px</BorderWidth><Padding>4px</Padding></Style>');
$styleReader->read();
$style = (new StyleParser())->parse($styleReader, new Style());
expectPixel([$style->borderColor->r, $style->borderColor->g, $style->borderColor->b], [17, 34, 51], 'BorderColor is available in inherited styles');
expectPixel($style->borderWidth, ['top' => '2px', 'right' => '3px', 'bottom' => '2px', 'left' => '3px'], 'BorderWidth accepts edge sizes');
expectPixel($style->margin, ['top' => '1px', 'right' => '2px', 'bottom' => '1px', 'left' => '2px'], 'Margin accepts edge sizes');
expectPixel($style->padding, ['top' => '4px', 'right' => '4px', 'bottom' => '4px', 'left' => '4px'], 'Padding accepts edge sizes');

try {
  (new ScreenParser())->parse('pixel-invalid.xml', new Style(), 'invalid', 'Invalid');
  throw new RuntimeException('Pixel box attribute was silently accepted.');
} catch (RuntimeException $error) {
  expectPixel(str_contains($error->getMessage(), "'boxPadding' attribute"), true, 'Pixel box edges must use Style elements');
}

$unsupported = new LayoutNode('vertical', '1*', '1*', pixelMode: true);
$unsupported->addLeaf(new LayoutLeaf('Text', '1*', '1*', new Text('Grid only')));
$unsupported->measureGrid(new Tile(0, 0, 10, 5));
try {
  $unsupported->measureArea(new Tile(0, 0, 10, 5), new WindowGeometry(8, 16, 80, 80, 0, 0));
  throw new RuntimeException('Grid-only widget silently entered a pixel layout.');
} catch (LogicException $error) {
  expectPixel(str_contains($error->getMessage(), 'pixel-painting'), true, 'Unsupported widget reports pixel requirement');
}

echo "Pixel layout checks passed\n";
