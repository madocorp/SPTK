<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Style, Texture, TextureContext};
use SPTK\Layout\Tile;
use SPTK\Rendering\PixelRenderer;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Canvas\{Canvas, Parser};

/** Check one canvas behavior. */
function expectCanvas(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Read a packed RGBA framebuffer pixel. */
function canvasPixel(SDL $sdl, FFI\CData $renderer, int $x, int $y): int {
  $surface = $sdl->ffi->SDL_RenderReadPixels($renderer, null);
  if ($surface === null) {
    throw new RuntimeException('Framebuffer read failed: ' . $sdl->error());
  }
  $rgba = $sdl->ffi->SDL_ConvertSurface($surface, SDL::SDL_PIXELFORMAT_RGBA8888);
  if ($rgba === null) {
    throw new RuntimeException('Framebuffer conversion failed.');
  }
  try {
    return FFI::cast('uint32_t*', $rgba->pixels)[$y * intdiv($rgba->pitch, 4) + $x];
  } finally {
    $sdl->ffi->SDL_DestroySurface($rgba);
    $sdl->ffi->SDL_DestroySurface($surface);
  }
}

/** Compose cropped sprite data through two transparent intermediate layers. */
function paintTestCanvas(Texture $surface, TextureContext $textures, Canvas $canvas): void {
  global $paints, $atlas, $layer, $factory, $lastSurface;
  $paints++;
  $factory = $textures;
  $lastSurface = $surface;
  if ($atlas === null) {
    $atlas = $textures->createTexture(4, 2);
    $atlas->fillRect(0, 0, 2, 2, '#ff0000');
    $atlas->fillRect(2, 0, 2, 2, '#00ff0080');
    $layer = $textures->createTexture(4, 4);
    $atlas->copy($layer, 2, 0, 0, 0, 2, 2, 4, 4);
  }
  $surface->clear('#000000');
  $layer->copyTo($surface, 0, 0);
  $atlas->copy($surface, 0, 0, 4, 0, 2, 2, 4, 4);
  $atlas->copy($surface, 0, 0, -2, 4, 2, 2, 4, 4);
}

/** Throw after drawing to test renderer state and cache recovery. */
function failTestCanvas(Texture $surface): void {
  $surface->clear('#123456');
  throw new RuntimeException('painter failure');
}

/** Parse a canvas XML fragment. */
function parseCanvas(string $xml): SPTK\Core\WidgetDefinition {
  $reader = new XMLReader();
  $reader->XML($xml);
  $reader->read();
  $parser = new Parser();
  $parser->validateAttributes($reader, ['id']);
  return $parser->parse($reader, new Style());
}

$definition = parseCanvas('<Canvas painter="FutureGame::paint"><Style><Background>#102030</Background></Style><Event type="keyDown" key="Space" action="FutureGame::move" /></Canvas>');
expectCanvas($definition->widget->background() == new Color(16, 32, 48), true, 'XML background');
expectCanvas(count($definition->events), 1, 'XML events');
foreach (['<Canvas painter="bad" />', '<Canvas unknown="1" />', '<Canvas><Point /></Canvas>'] as $xml) {
  try {
    parseCanvas($xml);
    throw new LogicException('Invalid XML accepted.');
  } catch (RuntimeException $error) {
  }
}
$paints = 0;
$atlas = $layer = $factory = $lastSurface = null;
$canvas = new Canvas('paintTestCanvas');
expectCanvas([$canvas->canActivate(), $canvas->handleInput(null), $canvas->paintsPixels()], [true, false, true], 'widget contract');
putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
expectCanvas($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), true, 'SDL initialized');
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
$window = $sdl->ffi->SDL_CreateWindow('Canvas test', 32, 24, SDL::SDL_WINDOW_HIDDEN);
$native = $sdl->ffi->SDL_CreateRenderer($window, null);
if ($window === null || $native === null) {
  throw new RuntimeException('Cannot create canvas test renderer: ' . $sdl->error());
}
$pixels = new PixelRenderer($native);
$ffi = $sdl->ffi;
try {
  $pixels->fill(new Tile(0, 0, 32, 24), new Color(0, 0, 255));
  $pixels->beginImages();
  $canvas->paintPixels($pixels, new Tile(3, 4, 8, 8), true);
  $pixels->endImages();
  expectCanvas($paints, 1, 'first visible paint');
  expectCanvas(canvasPixel($sdl, $native, 3, 4), 0x008000ff, 'alpha survives atlas and layer composition');
  expectCanvas(canvasPixel($sdl, $native, 7, 4), 0xff0000ff, 'cropped scaled sprite');
  expectCanvas(canvasPixel($sdl, $native, 3, 8), 0xff0000ff, 'negative destination clips locally');
  expectCanvas(canvasPixel($sdl, $native, 2, 8), 0x0000ffff, 'left sibling untouched');
  expectCanvas(canvasPixel($sdl, $native, 11, 4), 0x0000ffff, 'right sibling untouched');
  $surface = $lastSurface;
  $canvas->paintPixels($pixels, new Tile(3, 4, 8, 8), false);
  expectCanvas($paints, 1, 'focus shading reuses surface');
  expectCanvas(canvasPixel($sdl, $native, 7, 4), 0xbf0000ff, 'selection brightness');
  $canvas->invalidate();
  $event = $ffi->new('SDL_Event');
  $redraw = false;
  while ($ffi->SDL_PollEvent(FFI::addr($event))) {
    if ($event->type === SDL::SDL_EVENT_WINDOW_EXPOSED && $event->window->windowID === $ffi->SDL_GetWindowID($window)) {
      $redraw = true;
    }
  }
  expectCanvas($redraw, true, 'invalidation schedules redraw without keyboard input');
  $canvas->paintPixels($pixels, new Tile(3, 4, 8, 8), true);
  expectCanvas($paints, 2, 'explicit invalidation repaints');
  expectCanvas($lastSurface === $surface, true, 'same dimensions retain target');
  expectCanvas(canvasPixel($sdl, $native, 7, 4), 0xff0000ff, 'focus dimming leaves atlas colors intact');
  $canvas->paintPixels($pixels, new Tile(3, 4, 10, 8), true);
  expectCanvas([$paints, $surface->destroyed(), $atlas->destroyed()], [3, true, false], 'resize replaces surface and retains sprites');
  $canvas->paintPixels($pixels, new Tile(0, 0, 0, 8), true);
  expectCanvas($paints, 3, 'empty viewport does not invoke painter');
  $cropTarget = $factory->createTexture(6, 4, '#000000');
  $atlas->copy($cropTarget, -1, 0, 0, 0, 3, 2, 6, 4);
  $ffi->SDL_SetRenderTarget($native, $cropTarget->handle());
  expectCanvas(canvasPixel($sdl, $native, 0, 0), 0x000000ff, 'clipped source preserves destination alignment');
  expectCanvas(canvasPixel($sdl, $native, 2, 0), 0xff0000ff, 'clipped source preserves scale');
  $ffi->SDL_SetRenderTarget($native, null);
  $gd = imagecreatetruecolor(2, 1);
  imagealphablending($gd, false);
  imagesetpixel($gd, 0, 0, imagecolorallocatealpha($gd, 200, 0, 0, 63));
  imagesetpixel($gd, 1, 0, imagecolorallocatealpha($gd, 0, 0, 0, 127));
  $imported = $factory->textureFromImage($gd);
  imagedestroy($gd);
  $target = $factory->createTexture(2, 1, '#000000');
  $imported->copyTo($target, 0, 0);
  $stateTarget = $ffi->SDL_CreateTexture($native, SDL::SDL_PIXELFORMAT_RGBA8888, SDL::SDL_TEXTUREACCESS_TARGET, 32, 24);
  $ffi->SDL_SetRenderTarget($native, $target->handle());
  $red = (canvasPixel($sdl, $native, 0, 0) >> 24) & 255;
  expectCanvas(abs($red - 101) <= 1, true, 'GD source transparency');
  $ffi->SDL_SetRenderTarget($native, $stateTarget);
  $rect = $ffi->new('SDL_Rect');
  $rect->x = 1;
  $rect->y = 2;
  $rect->w = 20;
  $rect->h = 16;
  $ffi->SDL_SetRenderViewport($native, FFI::addr($rect));
  $rect->x = 2;
  $rect->y = 3;
  $rect->w = 8;
  $rect->h = 9;
  $ffi->SDL_SetRenderClipRect($native, FFI::addr($rect));
  $ffi->SDL_SetRenderDrawColor($native, 1, 2, 3, 4);
  $ffi->SDL_SetRenderDrawBlendMode($native, 0);
  $canvas->setPainter('failTestCanvas');
  try {
    $canvas->paintPixels($pixels, new Tile(0, 0, 10, 8), true);
    throw new LogicException('Painter failure lost.');
  } catch (RuntimeException $error) {
    expectCanvas($error->getMessage(), 'painter failure', 'painter exceptions propagate');
  }
  expectCanvas($ffi->SDL_GetRenderTarget($native) == $stateTarget, true, 'render target restored after failure');
  $ffi->SDL_GetRenderViewport($native, FFI::addr($rect));
  expectCanvas([$rect->x, $rect->y, $rect->w, $rect->h], [1, 2, 20, 16], 'viewport restored');
  $ffi->SDL_GetRenderClipRect($native, FFI::addr($rect));
  expectCanvas([$rect->x, $rect->y, $rect->w, $rect->h], [2, 3, 8, 9], 'clip restored');
  $rgba = $ffi->new('Uint8[4]');
  $ffi->SDL_GetRenderDrawColor($native, FFI::addr($rgba[0]), FFI::addr($rgba[1]), FFI::addr($rgba[2]), FFI::addr($rgba[3]));
  expectCanvas([$rgba[0], $rgba[1], $rgba[2], $rgba[3]], [1, 2, 3, 4], 'draw color restored');
  $blend = $ffi->new('int');
  $ffi->SDL_GetRenderDrawBlendMode($native, FFI::addr($blend));
  expectCanvas($blend->cdata, 0, 'draw blend mode restored');
  $ffi->SDL_SetRenderTarget($native, null);
  $ffi->SDL_SetRenderViewport($native, null);
  $ffi->SDL_SetRenderClipRect($native, null);
  $ffi->SDL_DestroyTexture($stateTarget);
  foreach (['readonly', 'self', 'foreign'] as $case) {
    try {
      if ($case === 'readonly') {
        $imported->clear();
      } else if ($case === 'self') {
        $atlas->copyTo($atlas, 0, 0);
      } else {
        $other = new TextureContext($native);
        $foreign = $other->createTexture(4, 4);
        $atlas->copyTo($foreign, 0, 0);
      }
      throw new RuntimeException('Invalid texture operation accepted.');
    } catch (LogicException $error) {
    } finally {
      if (isset($other)) {
        $other->close();
      }
    }
  }
  $canvas->setPainter('paintTestCanvas');
  $canvas->paintPixels($pixels, new Tile(0, 0, 10, 8), true);
  expectCanvas($paints, 4, 'successful repaint after callback failure');
  $pixels->beginImages();
  $pixels->endImages();
  expectCanvas([$lastSurface->destroyed(), $atlas->destroyed()], [true, false], 'hidden canvas frees surface but preserves atlas');
  $canvas->paintPixels($pixels, new Tile(0, 0, 10, 8), true);
  expectCanvas($paints, 5, 'returning to screen rebuilds surface');
  require_once APP_DIR . '/../Demo/Controller.php';
  $screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
  $demoCanvas = $screens[5]->widget('spriteCanvas');
  expectCanvas($demoCanvas instanceof Canvas, true, 'demo parses Canvas by widget id');
  $demoCanvas->paintPixels($pixels, new Tile(0, 0, 24, 24), true);
  $revision = $demoCanvas->revision();
  $layout = new SPTK\Layout\LayoutNode('vertical', '1*', '1*');
  $layout->addLeaf(new SPTK\Layout\LayoutLeaf('Canvas', '', '', $demoCanvas, [new SPTK\Events\EventDefinition('keyDown', 'space', 'Controller::advanceCanvas')]));
  $screen = new SPTK\Core\Screen($layout);
  $screen->measureGrid(new Tile(0, 0, 8, 2));
  $space = (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_SPACE, 'mod' => 0]];
  $return = (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => SDL::KEY_RETURN, 'mod' => 0]];
  $screen->handleEvent($space);
  expectCanvas($demoCanvas->revision(), $revision, 'canvas ignores input before activation');
  $screen->handleEvent($return);
  $screen->handleEvent($space);
  expectCanvas($demoCanvas->revision(), $revision + 1, 'activated canvas dispatches XML input action');
  $screen->handleEvent($return);
  $screen->handleEvent($space);
  expectCanvas($demoCanvas->revision(), $revision + 1, 'canvas ignores input after release');
  $pixels->close();
  expectCanvas([$atlas->destroyed(), $imported->destroyed(), $lastSurface->destroyed()], [true, true, true], 'window cleanup invalidates retained textures');
  $atlas->destroy();
  try {
    $factory->createTexture(2, 2);
    throw new RuntimeException('Closed texture factory accepted allocation.');
  } catch (LogicException $error) {
  }
} finally {
  $pixels->close();
  $ffi->SDL_DestroyRenderer($native);
  $ffi->SDL_DestroyWindow($window);
  $sdl->close();
}
echo "Canvas checks passed.\n";
