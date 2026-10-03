<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, RasterImage, Style};
use SPTK\Layout\Tile;
use SPTK\Rendering\{Grid, GridWriter, PixelRenderer};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Graph\{Axes, Data, Graph, Parser, Plot};

/** Check one graph behavior. */
function expectGraph(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Parse a graph XML fragment with normal attribute validation. */
function parseGraph(string $xml): SPTK\Core\WidgetDefinition {
  $reader = new XMLReader();
  $reader->XML($xml);
  $reader->read();
  $parser = new Parser();
  $parser->validateAttributes($reader, []);
  return $parser->parse($reader, new Style());
}

/** Read one packed graph raster pixel. */
function graphPixel(RasterImage $image, int $x, int $y): int {
  return unpack('L', substr($image->pixels, ($y * $image->width + $x) * 4, 4))[1];
}

/** Read one SDL framebuffer pixel and release the temporary surfaces. */
function graphFramebuffer(SDL $sdl, \FFI\CData $renderer, int $x, int $y): int {
  $surface = $sdl->ffi->SDL_RenderReadPixels($renderer, null);
  if ($surface === null) {
    throw new RuntimeException('Cannot read graph framebuffer.');
  }
  $rgba = $sdl->ffi->SDL_ConvertSurface($surface, SDL::SDL_PIXELFORMAT_RGBA8888);
  try {
    $pixels = \FFI::cast('uint32_t*', $rgba->pixels);
    return $pixels[$y * intdiv($rgba->pitch, 4) + $x];
  } finally {
    $sdl->ffi->SDL_DestroySurface($rgba);
    $sdl->ffi->SDL_DestroySurface($surface);
  }
}

$graph = new Graph([['points' => [[0, 2], ['1', '4']]]], ['title' => "Load\nchart"]);
expectGraph($graph->series()[0], ['name' => 'Series 1', 'type' => 'line', 'color' => '#00ffff', 'points' => [[0.0, 2.0], [1.0, 4.0]]], 'normalized series');
expectGraph($graph->options()['title'], 'Load chart', 'single-line title');
expectGraph([$graph->canActivate(), $graph->handleInput(null), $graph->paintsPixels()], [false, false, true], 'read-only pixel widget');
expectGraph([$graph->preferredWidth(), $graph->preferredHeight()], [40, 12], 'natural cell size');
$grid = new Grid(3, 2);
$graph->paint(new GridWriter($grid, new Tile(0, 0, 3, 2)));
expectGraph($grid->cell(1, 1)->bg == $graph->background(), true, 'clears character layer');
$image = $graph->raster(480, 280);
expectGraph([$image->width, $image->height, strlen($image->pixels)], [480, 280, 480 * 280 * 4], 'exact raster dimensions');
expectGraph($graph->raster(480, 280) === $image, true, 'unchanged paint reuses raster');
$graph->setSeries($graph->series());
$graph->setOptions($graph->options());
expectGraph($graph->raster(480, 280) === $image, true, 'unchanged setters preserve raster');
$previous = $graph->series();
foreach ([['type' => 'pie'], ['points' => [[1, INF]]], ['points' => [[1]]], ['type' => 'bar', 'points' => [[1, 2], [1, 3]]]] as $invalid) {
  try {
    $graph->setSeries([$invalid]);
    throw new RuntimeException('Invalid graph series accepted.');
  } catch (InvalidArgumentException $error) {
    expectGraph($graph->series(), $previous, 'invalid series preserves state');
  }
}
foreach ([['yMin' => 0], ['yMin' => 2, 'yMax' => 1], ['tickCount' => 1], ['grid' => 'yes'], ['other' => 1]] as $invalid) {
  $options = $graph->options();
  try {
    $graph->setOptions($invalid);
    throw new RuntimeException('Invalid graph options accepted.');
  } catch (InvalidArgumentException $error) {
    expectGraph($graph->options(), $options, 'invalid options preserve state');
  }
}
expectGraph($graph->raster(480, 280) === $image, true, 'invalid updates preserve raster');
$graph->addSeries(['type' => 'point', 'points' => [[0.5, 3]]]);
expectGraph($graph->raster(480, 280) !== $image, true, 'data update replaces raster');
$image = $graph->raster(480, 280);
$graph->setOptions(['grid' => true]);
expectGraph($graph->raster(480, 280) !== $image, true, 'options update replaces raster');
$image = $graph->raster(480, 280);
expectGraph($graph->raster(500, 280) !== $image, true, 'resize replaces raster');
$image = $graph->raster(500, 280);
expectGraph($graph->raster(500, 280, 20) !== $image, true, 'font metrics replace raster');
expectGraph(graphPixel($graph->raster(1, 1), 0, 0), 0x202630ff, 'tiny graph shows background');
expectGraph(Axes::ranges([], Data::DEFAULTS), ['xMin' => 0.0, 'xMax' => 1.0, 'yMin' => 0.0, 'yMax' => 1.0, 'barSpacing' => 1.0], 'empty automatic axes');
$constant = Axes::ranges(Data::series([['points' => [[3, 3]]]]), Data::DEFAULTS);
expectGraph($constant['xMin'] < 3 && $constant['xMax'] > 3, true, 'constant axes receive padding');
$bars = Data::series([
  ['type' => 'bar', 'color' => '#ff0000', 'points' => [[1, 4], [2, -4]]],
  ['type' => 'bar', 'color' => '#00ff00', 'points' => [[1, 2]]],
]);
$ranges = Axes::ranges($bars, Data::DEFAULTS);
expectGraph([$ranges['xMin'], $ranges['xMax'], $ranges['yMin'], $ranges['yMax'], $ranges['barSpacing']], [0.6, 2.4, -4.0, 4.0, 1.0], 'bars pad X and include negative Y');
$ticks = Axes::ticks(0, 100, 5, '%');
expectGraph(count($ticks) >= 2 && count($ticks) <= 5, true, 'round ticks respect target count');
expectGraph(str_ends_with($ticks[0][1], '%'), true, 'units follow ticks');
$ticks = Axes::ticks(1.0, 1.000000000000001, 5, '');
expectGraph(count(array_unique(array_column($ticks, 1))), count($ticks), 'narrow bounds retain distinct labels');
$extreme = new Graph([['points' => [[-PHP_FLOAT_MAX, -PHP_FLOAT_MAX], [PHP_FLOAT_MAX, PHP_FLOAT_MAX]]]]);
expectGraph($extreme->raster(320, 200)->width, 320, 'extreme finite values render');
$canvas = imagecreatetruecolor(101, 101);
$plot = new Plot();
$rect = [10, 10, 90, 90];
$bounds = ['xMin' => 0.0, 'xMax' => 10.0, 'yMin' => -5.0, 'yMax' => 5.0, 'barSpacing' => 1.0];
$plot->paint($canvas, Data::series([['color' => '#ff0000', 'points' => [[-10, 0], [20, 0]]]]), $bounds, $rect, 16);
expectGraph(imagecolorat($canvas, 50, 50), 0xff0000, 'line crossing plot with both endpoints outside');
expectGraph(imagecolorat($canvas, 9, 50), 0, 'line clipped at plot boundary');
imagefill($canvas, 0, 0, 0);
$plot->paint($canvas, $bars, ['xMin' => 0.0, 'xMax' => 3.0, 'yMin' => -5.0, 'yMax' => 5.0, 'barSpacing' => 1.0], $rect, 16);
expectGraph(imagecolorat($canvas, 30, 30), 0xff0000, 'first bar occupies first grouped slot');
expectGraph(imagecolorat($canvas, 41, 40), 0x00ff00, 'second bar occupies second grouped slot');
expectGraph(imagecolorat($canvas, 57, 70), 0xff0000, 'negative bar extends below zero');
expectGraph(imagecolorat($canvas, 68, 60), 0, 'missing value leaves reserved slot');
imagedestroy($canvas);
$definition = parseGraph('<Graph title="Mixed" grid="true" tickCount="4"><Series name="A"><Point x="0" y="2"></Point></Series><Style><Background>#102030</Background></Style><Event type="select" action="Controller::test" /></Graph>');
expectGraph([$definition->widget->options()['grid'], $definition->widget->options()['tickCount']], [true, 4], 'typed XML options');
expectGraph($definition->widget->series()[0]['points'], [[0.0, 2.0]], 'paired Point elements');
expectGraph($definition->widget->background() == new Color(16, 32, 48), true, 'XML style applies');
expectGraph(count($definition->events), 1, 'XML events retained');
foreach ([
  '<Graph unknown="1" />', '<Graph grid="perhaps" />', '<Graph tickCount="3.5" />',
  '<Graph><Series><Point x="0" /></Series></Graph>', '<Graph><Series><Point x="0" y="1e999" /></Series></Graph>',
  '<Graph><Series extra="1" /></Graph>', '<Graph><Series><Point x="0" y="1" extra="1" /></Series></Graph>',
  '<Graph><Series><Point x="0" y="1"><Point /></Point></Series></Graph>',
  '<Graph>text</Graph>', '<Graph><Series>text</Series></Graph>', '<Graph><Input /></Graph>',
] as $xml) {
  $rejected = false;
  try {
    parseGraph($xml);
  } catch (RuntimeException|InvalidArgumentException $error) {
    $rejected = true;
  }
  expectGraph($rejected, true, 'reject invalid XML ' . $xml);
}
$screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
$graphs = [];
foreach ($screens[5]->layout->leaves() as $leaf) {
  if ($leaf->instance() instanceof Graph) {
    $graphs[] = $leaf->instance();
  }
}
expectGraph(count($graphs), 2, 'Progress screen contains two graphs');
expectGraph([$graphs[0]->id(), $graphs[1]->id()], ['loadGraph', 'barGraph'], 'XML graph identifiers');
putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
expectGraph($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), true, 'dummy SDL initialization');
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
$window = $sdl->ffi->SDL_CreateWindow('Graph test', 520, 300, SDL::SDL_WINDOW_HIDDEN);
$native = $sdl->ffi->SDL_CreateRenderer($window, null);
$renderer = new PixelRenderer($native);
try {
  $area = new Tile(10, 10, 480, 280);
  $renderer->fill(new Tile(0, 0, 520, 300), new Color(10, 20, 30));
  $renderer->beginImages();
  $graphs[0]->paintPixels($renderer, $area, true);
  $renderer->endImages();
  expectGraph(graphFramebuffer($sdl, $native, 10, 10), 0x323232ff, 'graph image reaches SDL framebuffer');
  expectGraph(graphFramebuffer($sdl, $native, 9, 10), 0x0a141eff, 'graph stays inside pixel tile');
  $graphs[0]->paintPixels($renderer, $area, false);
  expectGraph(graphFramebuffer($sdl, $native, 10, 10) !== 0x323232ff, true, 'unselected graph uses image shading');
  $graphs[0]->setOptions(['title' => 'Updated']);
  $renderer->beginImages();
  $graphs[0]->paintPixels($renderer, $area, true);
  $renderer->endImages();
  expectGraph(count((new ReflectionProperty(PixelRenderer::class, 'images'))->getValue($renderer)), 1, 'old graph texture released after update');
} finally {
  $renderer->close();
  $sdl->ffi->SDL_DestroyRenderer($native);
  $sdl->ffi->SDL_DestroyWindow($window);
  $sdl->close();
}
echo "Graph checks passed\n";
