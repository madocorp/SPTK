<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, WidgetSelection};
use SPTK\Layout\{LayoutLeaf, Tile};
use SPTK\Widgets\Empty\Placeholder;

/** Assert a named navigation result. */
function expectNavigation(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Rotate or reflect a rightward test tile to exercise each arrow direction. */
function navigationTile(array $rectangle, string $direction): Tile {
  [$x, $y, $width, $height] = $rectangle;
  return match ($direction) {
    'left' => new Tile(100 - $x - $width, $y, $width, $height),
    'up' => new Tile($y, 100 - $x - $width, $height, $width),
    'down' => new Tile($y, $x, $height, $width),
    default => new Tile($x, $y, $width, $height),
  };
}

$cases = [
  'aligned widget beats a closer diagonal' => [
    [[20, 20, 10, 10], [50, 20, 10, 10], [31, 31, 10, 10]], 1,
  ],
  'nearest aligned widget wins regardless of layout order' => [
    [[20, 20, 10, 10], [60, 20, 10, 10], [40, 20, 10, 10], [31, 31, 10, 10]], 2,
  ],
  'partial row or column overlap counts as aligned' => [
    [[20, 20, 10, 10], [50, 29, 10, 10], [31, 31, 10, 10]], 1,
  ],
  'touching row or column edges use geometric fallback' => [
    [[20, 20, 10, 10], [50, 30, 10, 10], [31, 31, 10, 10]], 2,
  ],
  'fallback considers both geometric axes' => [
    [[20, 20, 10, 10], [31, 60, 10, 10], [40, 32, 10, 10]], 2,
  ],
  'fallback excludes widgets behind the arrow' => [
    [[20, 20, 10, 10], [50, 35, 10, 10], [9, 20, 10, 10]], 1,
  ],
  'equidistant aligned widgets prefer matching edges' => [
    [[20, 20, 10, 10], [40, 15, 10, 5], [40, 25, 10, 5], [40, 20, 10, 5]], 3,
  ],
  'no widget forward keeps focus' => [
    [[20, 20, 10, 10], [9, 20, 10, 10], [20, 40, 10, 10]], 0,
  ],
];
foreach (['left', 'right', 'up', 'down'] as $direction) {
  foreach ($cases as $name => [$rectangles, $expectedIndex]) {
    $leaves = [];
    foreach ($rectangles as $rectangle) {
      $leaf = new LayoutLeaf('Empty', '', '', new Placeholder(new Color(0, 0, 0)));
      $leaf->setGrid(navigationTile($rectangle, $direction));
      $leaves[] = $leaf;
    }
    $selection = new WidgetSelection($leaves);
    expectNavigation($selection->move($direction), $expectedIndex !== 0, $direction . ': ' . $name . ' movement');
    expectNavigation($selection->selectedLeaf() === $leaves[$expectedIndex], true, $direction . ': ' . $name);
  }
}
$selection = new WidgetSelection([]);
expectNavigation($selection->move('right'), false, 'empty screen keeps focus');
echo "Navigation checks passed\n";
