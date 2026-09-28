<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator, Tile, WindowGeometry};
use SPTK\Widgets\Empty\Placeholder;

/** Check a measured rectangle against explicit pixel coordinates. */
function expectGeometryArea(Tile $area, array $expected, string $name): void {
  $actual = [$area->x, $area->y, $area->width, $area->height];
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
  }
}

/** Read a layout element's measured background or separator area. */
function measuredGeometryArea(LayoutLeaf|LayoutSeparator $element): Tile {
  return (new ReflectionProperty($element, 'area'))->getValue($element);
}

$grid = new Tile(0, 0, 10, 6);
$geometry = new WindowGeometry(8, 15, 96, 105, 8, 7);
$interior = new Tile(3, 2, 2, 2);
expectGeometryArea($geometry->pixelArea($interior), [32, 37, 16, 30], 'content uses cell size and offset');
expectGeometryArea($geometry->backgroundArea($grid, $grid), [0, 0, 96, 105], 'root background covers the window');
expectGeometryArea($geometry->backgroundArea($interior, $grid), [24, 30, 32, 45], 'interior padding with odd cell height');
expectGeometryArea($geometry->backgroundArea(new Tile(0, 0, 2, 2), $grid), [0, 0, 32, 45], 'top and left window edges');
expectGeometryArea($geometry->backgroundArea(new Tile(8, 4, 2, 2), $grid), [64, 60, 32, 45], 'bottom and right window edges');
expectGeometryArea($geometry->pixelArea(new Tile(3, 2, 0, 0)), [32, 37, 0, 0], 'empty content rectangle');
expectGeometryArea($geometry->backgroundArea(new Tile(3, 2, 0, 0), $grid), [24, 30, 16, 15], 'empty tile retains padding');
$even = new WindowGeometry(8, 14, 96, 98, 8, 7);
expectGeometryArea($even->backgroundArea($interior, $grid), [24, 28, 32, 42], 'even cell height padding');
$parent = new Tile(3, 2, 7, 4);
expectGeometryArea($geometry->separatorArea($parent, new Tile(3, 2, 2, 4), $grid, 'horizontal'), [55, 30, 2, 75], 'vertical separator spans parent padding');
expectGeometryArea($geometry->separatorArea($parent, new Tile(3, 2, 7, 1), $grid, 'vertical'), [24, 59, 72, 2], 'horizontal separator rounds odd cell height');
$small = new WindowGeometry(8, 15, 2, 3, -3, -6);
$oneCell = new Tile(0, 0, 1, 1);
expectGeometryArea($small->pixelArea($oneCell), [-3, -6, 8, 15], 'small window keeps negative centered offsets');
expectGeometryArea($small->backgroundArea($oneCell, $oneCell), [0, 0, 2, 3], 'small window background stays within its edges');
$root = new LayoutNode('horizontal', '1*', '1*');
$left = new LayoutLeaf('Empty', '2', '1*', new Placeholder(new Color(0, 0, 0)));
$right = new LayoutNode('vertical', '1*', '1*');
$top = new LayoutLeaf('Empty', '1*', '2', new Placeholder(new Color(0, 0, 0)));
$bottom = new LayoutLeaf('Empty', '1*', '1*', new Placeholder(new Color(0, 0, 0)));
$verticalSeparator = new LayoutSeparator();
$horizontalSeparator = new LayoutSeparator();
$right->addLeaf($top);
$right->addSeparator($horizontalSeparator);
$right->addLeaf($bottom);
$root->addLeaf($left);
$root->addSeparator($verticalSeparator);
$root->addNode($right);
$screen = new Screen($root);
$screen->measureGrid($grid);
$screen->measureArea($grid, $geometry);
expectGeometryArea(measuredGeometryArea($left), [0, 0, 32, 105], 'left leaf measured through Screen');
expectGeometryArea(measuredGeometryArea($top), [32, 0, 64, 45], 'nested top leaf background');
expectGeometryArea(measuredGeometryArea($bottom), [32, 45, 64, 60], 'nested bottom leaf background');
expectGeometryArea(measuredGeometryArea($verticalSeparator), [31, 0, 2, 105], 'root separator measured through tree');
expectGeometryArea(measuredGeometryArea($horizontalSeparator), [32, 44, 64, 2], 'nested separator measured through tree');
$resized = new WindowGeometry(8, 15, 100, 109, 10, 9);
$screen->measureArea($grid, $resized);
expectGeometryArea(measuredGeometryArea($left), [0, 0, 34, 109], 'resize updates leaf padding');
expectGeometryArea(measuredGeometryArea($top), [34, 0, 66, 47], 'resize updates nested leaf padding');
expectGeometryArea(measuredGeometryArea($bottom), [34, 47, 66, 62], 'resize updates bottom window edge');
expectGeometryArea(measuredGeometryArea($verticalSeparator), [33, 0, 2, 109], 'resize updates root separator');
expectGeometryArea(measuredGeometryArea($horizontalSeparator), [34, 46, 66, 2], 'resize updates nested separator');
expectGeometryArea($geometry->pixelArea($interior), [32, 37, 16, 30], 'previous geometry remains unchanged');
echo "Window geometry checks passed\n";
