<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\Color;
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile, WindowGeometry};
use SPTK\Rendering\Grid;
use SPTK\Widgets\Empty\Placeholder;

/** Check a layout's automatic unoccupied tile after measurement. */
function expectRemainder(LayoutNode $layout, ?array $expected, string $message): void {
  $leaf = (new ReflectionProperty($layout, 'remainder'))->getValue($layout);
  $tile = $leaf?->grid();
  $actual = $tile === null ? null : [$tile->x, $tile->y, $tile->width, $tile->height];
  if ($actual !== $expected) {
    throw new RuntimeException($message . ': ' . json_encode($actual));
  }
}

$background = Color::from('#405060');
$layout = new LayoutNode('vertical', '1*', '1*');
$layout->setGridBackground($background);
$first = new LayoutLeaf('Empty', '1*', '2', new Placeholder($background));
$layout->addLeaf($first);
$layout->measureGrid(new Tile(0, 0, 4, 6));
expectRemainder($layout, [0, 2, 4, 4], 'vertical remainder fills every unused row');
if (count($layout->leaves()) !== 1 || count($layout->movementLeaves()) !== 1) {
  throw new RuntimeException('Automatic remainder must stay out of screen indexing and navigation.');
}
$grid = new Grid(4, 6);
$layout->paint($grid, $first);
if ($grid->cell(0, 1)->bg != $background || $grid->cell(0, 2)->bg != $background->darkened() || $grid->cell(0, 5)->bg != $background->darkened()) {
  throw new RuntimeException('Unoccupied rows must dim while a widget is selected.');
}
$layout->measureArea(new Tile(0, 0, 4, 6), new WindowGeometry(8, 16, 32, 96, 0, 0));
$remainder = (new ReflectionProperty($layout, 'remainder'))->getValue($layout);
$area = $remainder->paintedArea();
if ($area->y + $area->height !== 96) {
  throw new RuntimeException('Remainder background must reach the tile bottom.');
}
$layout->measureGrid(new Tile(0, 0, 4, 2));
expectRemainder($layout, null, 'fitted layout has no remainder');

$horizontal = new LayoutNode('horizontal', '1*', '1*');
$horizontal->addLeaf(new LayoutLeaf('Empty', '2', '1*', new Placeholder($background)));
$horizontal->measureGrid(new Tile(1, 3, 8, 2));
expectRemainder($horizontal, [3, 3, 6, 2], 'horizontal remainder fills unused columns');

$flexible = new LayoutNode('vertical', '1*', '1*');
$flexible->addLeaf(new LayoutLeaf('Empty', '1*', '1*', new Placeholder($background)));
$flexible->measureGrid(new Tile(0, 0, 4, 6));
expectRemainder($flexible, null, 'weighted child already fills its tile');

echo "Layout remainder checks passed\n";
