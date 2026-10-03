<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen, Style, WidgetSelection};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\Grid;
use SPTK\Widgets\Empty\Placeholder;
use SPTK\Widgets\Input\Input;

/** Count actions from a button omitted during arrow movement. */
final class NavigationAction {

  public static int $calls = 0;

  /** Record one hotkey press. */
  public static function press(): void {
    self::$calls++;
  }

}

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

/** Create one arrow or activation event for nested navigation checks. */
function navigationKey(int $key): object {
  return (object)['type' => \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0]];
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
$hidden = new LayoutLeaf('Empty', '', '', new Placeholder(new Color(0, 0, 0)));
$left = new LayoutLeaf('Empty', '', '', new Placeholder(new Color(0, 0, 0)));
$right = new LayoutLeaf('Empty', '', '', new Placeholder(new Color(0, 0, 0)));
$left->setGrid(new Tile(0, 0, 10, 10));
$right->setGrid(new Tile(20, 0, 10, 10));
$key = (object)['type' => \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => \SPTK\SDLWrapper\SDL::KEY_RIGHT, 'mod' => 0, 'repeat' => false]];
$group = new LayoutNode('vertical', '', '', navigateChildren: false, id: 'preview');
$group->addLeaf($hidden);
$groupRoot = new LayoutNode('horizontal', '', '');
$groupRoot->addLeaf($left);
$groupRoot->addNode($group);
$groupRoot->addLeaf($right);
$screen = new Screen($groupRoot);
$group->setGrid(new Tile(10, 0, 10, 10));
$screen->handleEvent($key);
$groupFocus = $screen->selectedLeaf();
expectNavigation($groupFocus->instance()->id(), 'preview', 'layout remains one selectable tile');
expectNavigation($groupFocus->instance()->canActivate(), false, 'layout focus keeps arrows in navigation mode');
expectNavigation($groupRoot->focusedLeaves($groupFocus), [$hidden], 'layout focus highlights rendered descendants');
expectNavigation($screen->widget('preview') === $groupFocus->instance(), true, 'layout focus is available by ID');
$screen->setLayout($groupRoot);
expectNavigation($screen->selectedLeaf() === $groupFocus, true, 'layout focus survives reindexing');
$screen->handleEvent($key);
expectNavigation($screen->selectedLeaf() === $right, true, 'next arrow leaves group without visiting descendants');
$singleRoot = new LayoutNode('vertical', '', '', navigateChildren: false, id: 'single');
$singleRoot->addLeaf($hidden);
$screen->setLayout($singleRoot);
expectNavigation($screen->selectedLeaf()->instance()->id(), 'single', 'grouped root remains reachable as initial focus');
expectNavigation(count($singleRoot->leaves()), 1, 'grouped root retains rendering leaves');
$skipScreen = (new SPTK\XmlParser\ScreenParser())->parse('navigation.xml', new Style(), 'navigation', 'Navigation');
$skipScreen->measureGrid(new Tile(0, 0, 24, 4));
expectNavigation($skipScreen->selectedLeaf()->instance()->id(), 'first', 'non-navigated first tile remains initially selected');
expectNavigation($skipScreen->widget('middle') !== null, true, 'skipped widget remains available by ID');
expectNavigation($skipScreen->widget('inside') !== null, true, 'skipped group child remains available by ID');
expectNavigation($skipScreen->widget('nested') !== null, true, 'skipped layout child remains available by ID');
expectNavigation($skipScreen->widget('quick') !== null, true, 'skipped button remains available by ID');
$skipScreen->handleEvent($key);
expectNavigation($skipScreen->selectedLeaf()->instance()->id(), 'last', 'arrow skips widgets and layouts');
$skipScreen->handleEvent((object)['type' => \SPTK\SDLWrapper\SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => ord('b'), 'mod' => 0]]);
$hotkeyText = \FFI::new('char[2]');
\FFI::memcpy($hotkeyText, 'b', 1);
$skipScreen->handleEvent((object)['type' => \SPTK\SDLWrapper\SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $hotkeyText]]);
expectNavigation(NavigationAction::$calls, 1, 'skipped button hotkey still works');
$targets = $skipScreen->layout->leaves(true);
$selection = new WidgetSelection($targets, $skipScreen->layout->movementLeaves());
$selection->select($targets[2]);
expectNavigation($selection->selectedLeaf()->instance()->id(), 'group', 'skipped group supports explicit selection');
expectNavigation($selection->move('right'), true, 'explicitly selected skipped group can move out');
expectNavigation($selection->selectedLeaf()->instance()->id(), 'last', 'movement from skipped group reaches next target');
$skipScreen->selectLeaf($targets[2]);
$skipScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($skipScreen->navigationDepth(), 1, 'XML enterChildren opens a skipped but explicitly selected group');
expectNavigation($skipScreen->selectedLeaf()->instance()->id(), 'inside', 'XML group entry selects its child');
$skipScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_ESCAPE));
$ink = new Color(200, 100, 100);
$outer = new LayoutNode('horizontal', '', '');
$before = new LayoutLeaf('Empty', '4', '', new Placeholder($ink));
$after = new LayoutLeaf('Empty', '4', '', new Placeholder($ink));
$container = new LayoutNode('vertical', '4', '', navigateChildren: false, id: 'container', enterChildren: true);
$first = new LayoutLeaf('Empty', '', '1', new Placeholder($ink));
$second = new LayoutLeaf('Empty', '', '1', new Placeholder($ink));
$container->addLeaf($first);
$container->addLeaf($second);
$outer->addLeaf($before);
$outer->addNode($container);
$outer->addLeaf($after);
$nestedScreen = new Screen($outer);
$nestedScreen->measureGrid(new Tile(0, 0, 16, 3));
$grid = new Grid(16, 3);
$nestedScreen->paint($grid);
expectNavigation($grid->cell(6, 0)->bg->r, 150, 'unfocused container darkens first child');
expectNavigation($grid->cell(6, 2)->bg->r, 150, 'unfocused container darkens second child');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RIGHT));
expectNavigation($nestedScreen->selectedLeaf()->instance()->id(), 'container', 'outer arrow selects container as one tile');
$nestedScreen->paint($grid);
expectNavigation($grid->cell(6, 0)->bg->r, 200, 'selected container lights first child');
expectNavigation($grid->cell(6, 2)->bg->r, 200, 'selected container lights second child');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($nestedScreen->navigationDepth(), 1, 'Return enters container');
expectNavigation($nestedScreen->selectedLeaf() === $first, true, 'entry selects first child');
$nestedScreen->paint($grid);
expectNavigation($grid->cell(6, 0)->bg->r, 200, 'entered container lights selected child');
expectNavigation($grid->cell(6, 2)->bg->r, 150, 'entered container darkens other child');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_DOWN));
expectNavigation($nestedScreen->selectedLeaf() === $second, true, 'arrows move inside container');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RIGHT));
expectNavigation($nestedScreen->selectedLeaf() === $second, true, 'arrow cannot leave entered container');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_ESCAPE));
expectNavigation($nestedScreen->navigationDepth(), 0, 'Escape leaves container');
expectNavigation($nestedScreen->selectedLeaf()->instance()->id(), 'container', 'exit restores container focus');
$nestedScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($nestedScreen->selectedLeaf() === $second, true, 'reentry remembers selected child');
$nestedScreen->setLayout($outer);
expectNavigation($nestedScreen->navigationDepth(), 0, 'layout replacement leaves nested navigation');
expectNavigation($nestedScreen->selectedLeaf()->instance()->id(), 'container', 'layout replacement keeps outer focus');
$inner = new LayoutNode('vertical', '', '1*', navigateChildren: false, id: 'inner', enterChildren: true);
$input = new Input('value');
$inner->addLeaf(new LayoutLeaf('Input', '', '', $input));
$outerGroup = new LayoutNode('vertical', '', '', navigateChildren: false, id: 'outer', enterChildren: true);
$outerGroup->addNode($inner);
$scopeScreen = new Screen($outerGroup);
$scopeScreen->measureGrid(new Tile(0, 0, 10, 2));
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($scopeScreen->selectedLeaf()->instance()->id(), 'inner', 'first Return enters outer group');
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($scopeScreen->navigationDepth(), 2, 'second Return enters nested group');
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_RETURN));
expectNavigation($input->editing(), true, 'third Return activates child widget');
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_ESCAPE));
expectNavigation($input->editing(), false, 'first Escape releases active child');
expectNavigation($scopeScreen->navigationDepth(), 2, 'releasing child keeps nested scope');
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_ESCAPE));
expectNavigation($scopeScreen->navigationDepth(), 1, 'second Escape leaves nested group');
$scopeScreen->handleEvent(navigationKey(\SPTK\SDLWrapper\SDL::KEY_ESCAPE));
expectNavigation($scopeScreen->navigationDepth(), 0, 'third Escape leaves outer group');
echo "Navigation checks passed\n";
