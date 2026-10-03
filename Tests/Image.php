<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, ImageSource, Screen, Style, Window};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\{Font, GlyphAtlas, Grid, GridWriter, PixelRenderer};
use SPTK\SDLWrapper\SDL;
use SPTK\SDLWrapper\TTF;
use SPTK\Widgets\Image\Image;
use SPTK\Widgets\Text\Text;

/** Assert a named image behavior. */
function expectImage(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Read one framebuffer pixel as a packed RGBA value. */
function imagePixel(SDL $sdl, \FFI\CData $renderer, int $x, int $y): int {
  $surface = $sdl->ffi->SDL_RenderReadPixels($renderer, null);
  if ($surface === null) {
    throw new RuntimeException('SDL_RenderReadPixels failed: ' . $sdl->error());
  }
  $rgba = $sdl->ffi->SDL_ConvertSurface($surface, SDL::SDL_PIXELFORMAT_RGBA8888);
  if ($rgba === null) {
    $sdl->ffi->SDL_DestroySurface($surface);
    throw new RuntimeException('SDL_ConvertSurface failed: ' . $sdl->error());
  }
  try {
    $pixels = \FFI::cast('uint32_t*', $rgba->pixels);
    return $pixels[$y * intdiv($rgba->pitch, 4) + $x];
  } finally {
    $sdl->ffi->SDL_DestroySurface($rgba);
    $sdl->ffi->SDL_DestroySurface($surface);
  }
}

/** Read a retained window frame after presentation without depending on backbuffer contents. */
function windowImagePixel(SDL $sdl, Window $window, int $x, int $y): int {
  $renderer = (new ReflectionProperty(Window::class, 'ffiRenderer'))->getValue($window);
  $frame = (new ReflectionProperty(Window::class, 'frameTexture'))->getValue($window);
  $previousTarget = $sdl->ffi->SDL_GetRenderTarget($renderer);
  $sdl->checkReturnValue($sdl->ffi->SDL_SetRenderTarget($renderer, $frame), 'SDL_SetRenderTarget');
  try {
    return imagePixel($sdl, $renderer, $x, $y);
  } finally {
    $sdl->checkReturnValue($sdl->ffi->SDL_SetRenderTarget($renderer, $previousTarget), 'SDL_SetRenderTarget');
  }
}

/** Create a keyboard event for image controls. */
function imageKey(int $key, int $mod = 0, int $scancode = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod, 'scancode' => $scancode]];
}

/** Build a key event for a symbol using SDL's current keyboard layout and modifiers. */
function imageSymbolKey(int $symbol): object {
  $sdl = SPTK\App::sdl();
  $mod = $sdl->ffi->new('SDL_Keymod');
  $scancode = (int)$sdl->ffi->SDL_GetScancodeFromKey($symbol, FFI::addr($mod));
  if ($scancode === 0) {
    throw new RuntimeException('Current keyboard layout cannot produce image control symbol.');
  }
  $base = (int)$sdl->ffi->SDL_GetKeyFromScancode($scancode, (int)$mod->cdata, true);
  return imageKey($base, (int)$mod->cdata, $scancode);
}

/** Build a small transparent fixture independent of the demo's chosen photograph. */
function imageFixture(): string {
  $path = tempnam(sys_get_temp_dir(), 'sptk-image-');
  $canvas = imagecreatetruecolor(96, 64);
  imagealphablending($canvas, false);
  imagesavealpha($canvas, true);
  imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
  imagefilledrectangle($canvas, 16, 12, 80, 52, imagecolorallocatealpha($canvas, 200, 100, 50, 0));
  imagepng($canvas, $path);
  imagedestroy($canvas);
  register_shutdown_function('removeImageFixture', $path);
  return $path;
}

/** Remove the temporary fixture created by this test run. */
function removeImageFixture(string $path): void {
  unlink($path);
}

$source = imageFixture();
$image = new Image($source);
$shared = ImageSource::from($source);
$rasterProperty = new ReflectionProperty(ImageSource::class, 'image');
expectImage($rasterProperty->getValue($shared), null, 'construction leaves pixels unloaded');
expectImage([$image->preferredWidth(), $image->preferredHeight()], [12, 4], 'header dimensions preserve natural sizing');
$titleStyle = new Style(background: new Color(12, 24, 36), highlight: new Color(80, 160, 200));
$titled = new Image($source, bg: $titleStyle->background, title: 'Preview', style: $titleStyle);
expectImage([$titled->preferredWidth(), $titled->preferredHeight()], [12, 5], 'title adds one preferred row');
$titleGrid = new Grid(12, 3);
$titled->paint(new GridWriter($titleGrid, new Tile(0, 0, 12, 3)));
expectImage([$titleGrid->cell(0, 0)->glyph, $titleGrid->cell(0, 0)->fg, $titleGrid->cell(0, 0)->bg], ['P', $titleStyle->highlight, $titleStyle->background], 'title uses inherited highlight');
expectImage($titleGrid->cell(0, 1)->glyph, ' ', 'title occupies only one row');
$cellArea = new Tile(10, 20, 100, 80);
$outerArea = new Tile(4, 14, 112, 92);
expectImage($image->pixelArea($cellArea, $outerArea) == $cellArea, true, 'untitled image retains padded area');
expectImage($titled->pixelArea($cellArea, $outerArea) == new Tile(10, 36, 100, 64), true, 'title reserves image viewport below grid heading');
$edgeTitle = new Image($source, fit: 'cover', padding: false, title: 'Edge');
expectImage($edgeTitle->pixelArea($cellArea, $outerArea) == new Tile(4, 36, 112, 70), true, 'unpadded cover retains side and bottom margins below title');
$emptyTitle = new Image($source, title: '');
expectImage($emptyTitle->pixelArea($cellArea, $outerArea) == new Tile(10, 36, 100, 64), true, 'empty image title still reserves a row');
expectImage($rasterProperty->getValue($shared), null, 'layout reads dimensions without decoding');
$image->destination(new Tile(0, 0, 200, 100));
expectImage($rasterProperty->getValue($shared), null, 'positioning avoids decoding');
$alias = new Image(dirname($source) . '/./' . basename($source));
expectImage($image->source() === $alias->source(), true, 'equivalent paths share one raster');
expectImage([$image->source()->width, $image->source()->height], [96, 64], 'raster dimensions');
expectImage($image->source()->src, $source, 'path source');
$rect = $image->destination(new Tile(0, 0, 200, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [52, 18, 96, 64], 'native centered image');
$rect = $image->destination(new Tile(0, 0, 48, 32));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [-24, -16, 96, 64], 'manual image keeps native size');
$zoomed = new Image($source, zoom: 2, x: 10, y: -5);
$rect = $zoomed->destination(new Tile(0, 0, 200, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [4, -19, 192, 128], 'fitting width ignores x offset');
$small = new Image($source, x: 30, y: -20);
$largeViewport = new Tile(0, 0, 200, 100);
$small->destination($largeViewport);
foreach ([SDL::KEY_LEFT, SDL::KEY_RIGHT, SDL::KEY_UP, SDL::KEY_DOWN, SDL::KEY_HOME, SDL::KEY_END, SDL::KEY_PAGEUP, SDL::KEY_PAGEDOWN] as $key) {
  $small->handleInput(imageKey($key));
  $rect = $small->destination($largeViewport);
  expectImage([$rect->x, $rect->y], [52, 18], 'small image stays centered after navigation key ' . $key);
}
expectImage((new ReflectionProperty(Image::class, 'x'))->getValue($small), 30, 'horizontal offset remains unchanged while image fits');
expectImage((new ReflectionProperty(Image::class, 'y'))->getValue($small), -20, 'vertical offset remains unchanged while image fits');
$partiallyFitting = new Image($source, y: 20);
$narrowViewport = new Tile(0, 0, 60, 100);
$rect = $partiallyFitting->destination($narrowViewport);
expectImage([$rect->x, $rect->y], [-18, 18], 'fitting height centers independently');
foreach ([SDL::KEY_UP, SDL::KEY_DOWN, SDL::KEY_PAGEUP, SDL::KEY_PAGEDOWN] as $key) {
  $partiallyFitting->handleInput(imageKey($key));
  expectImage($partiallyFitting->destination($narrowViewport)->y, 18, 'fitting height ignores navigation key ' . $key);
}
expectImage((new ReflectionProperty(Image::class, 'y'))->getValue($partiallyFitting), 20, 'fitting height keeps vertical offset');
$partiallyFitting->handleInput(imageKey(SDL::KEY_RIGHT));
expectImage($partiallyFitting->destination($narrowViewport)->x, -36, 'oversized width still pans');
$smallViewport = new Tile(0, 0, 48, 32);
$overscrolled = new Image($source, zoom: 2, x: 999, y: -999);
$rect = $overscrolled->destination($smallViewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, -96, 192, 128], 'initial offsets stop at image edges');
$zoomedAtFirstEdge = new Image($source, zoom: 2, x: 72, y: 48);
$zoomedAtFirstEdge->destination($smallViewport);
$zoomedAtFirstEdge->handleInput(imageKey(SDL::KEY_MINUS));
$rect = $zoomedAtFirstEdge->destination($smallViewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, 0, 154, 102], 'zooming out keeps first edges covered');
$zoomedAtLastEdge = new Image($source, zoom: 2, x: -72, y: -48);
$zoomedAtLastEdge->destination($smallViewport);
$zoomedAtLastEdge->handleInput(imageKey(SDL::KEY_MINUS));
$rect = $zoomedAtLastEdge->destination($smallViewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [-106, -70, 154, 102], 'zooming out keeps last edges covered');
$growing = new Image($source, x: 999, y: -999);
$growingViewport = new Tile(0, 0, 100, 80);
$growing->destination($growingViewport);
$growing->handleInput(imageKey(SDL::KEY_PLUS));
$rect = $growing->destination($growingViewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, 0, 120, 80], 'zooming beyond fitting width clamps stored offset');
expectImage((new ReflectionProperty(Image::class, 'y'))->getValue($growing), -999, 'fitting height retains its offset');
$growing->handleInput(imageKey(SDL::KEY_PLUS));
expectImage($growing->destination($growingViewport)->y, -20, 'zooming beyond fitting height clamps stored offset');
$fill = new Image($source, fill: true);
$rect = $fill->destination(new Tile(0, 0, 200, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [52, 18, 96, 64], 'fill keeps smaller image native');
$rect = $fill->destination(new Tile(0, 0, 48, 32));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, 0, 48, 32], 'fill shrinks oversized image');
$cover = new Image($source, fit: 'cover', interactive: false, padding: false);
$rect = $cover->destination(new Tile(0, 0, 200, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, -16, 200, 133], 'cover enlarges uniformly and crops vertically');
$rect = $cover->destination(new Tile(0, 0, 40, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [-55, 0, 150, 100], 'cover crops horizontally after portrait resize');
expectImage($cover->destination(new Tile(0, 0, 0, 0))->width, 0, 'cover supports empty viewport');
expectImage([$cover->canActivate(), $cover->pixelPadding()], [false, false], 'display-only unpadded image flags');
$originalCover = $cover->destination(new Tile(0, 0, 200, 100));
foreach ([SDL::KEY_PLUS, SDL::KEY_MINUS, SDL::KEY_LEFT, SDL::KEY_EQUALS, SDL::KEY_SPACE] as $key) {
  expectImage($cover->handleInput(imageKey($key)), false, 'disabled image ignores viewer control');
}
expectImage($cover->destination(new Tile(0, 0, 200, 100)) == $originalCover, true, 'disabled controls preserve cover composition');
$cover->setSource($source);
expectImage($cover->destination(new Tile(0, 0, 200, 100)) == $originalCover, true, 'replacement retains configured cover mode');
$contain = new Image($source, fit: 'contain');
$rect = $contain->destination(new Tile(0, 0, 200, 100));
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [25, 0, 150, 100], 'contain enlarges uniformly while keeping entire image visible');
expectImage([$image->canActivate(), $image->pixelPadding()], [true, true], 'legacy image retains activation and padding');
$displayLayout = new LayoutNode('horizontal', '1*', '1*');
$displayLayout->addLeaf(new LayoutLeaf('Image', '1*', '', $cover));
$displayLayout->addLeaf(new LayoutLeaf('Text', '1*', '', new Text('Aside')));
$displayScreen = new Screen($displayLayout);
$displayScreen->measureGrid(new Tile(0, 0, 20, 4));
$displayScreen->handleEvent(imageKey(SDL::KEY_RETURN));
expectImage($displayScreen->activeLeaf(), null, 'Return cannot activate display-only image');
$displayScreen->handleEvent(imageKey(SDL::KEY_RIGHT));
expectImage($displayScreen->selectedLeaf()->instance() instanceof Text, true, 'display-only image preserves tile navigation');
$reader = new XMLReader();
$reader->XML('<Image src="' . $source . '" fit="cover" padding="false" interactive="false" title="Cover" />');
$reader->read();
$imageParser = new SPTK\Widgets\Image\Parser();
$imageParser->validateAttributes($reader, []);
$parsedCover = $imageParser->parse($reader, new SPTK\Core\Style())->widget;
expectImage([$parsedCover->canActivate(), $parsedCover->pixelPadding()], [false, false], 'XML flags configure display-only unpadded cover');
expectImage($parsedCover->pixelArea($cellArea, $outerArea) == new Tile(4, 36, 112, 70), true, 'XML title reserves pixel content below heading');
expectImage($parsedCover->destination(new Tile(0, 0, 200, 100)) == $originalCover, true, 'XML cover mode preserves aspect ratio');
foreach (['fit="stretch"', 'fit="cover" fill="true"', 'fit="cover" zoom="2"', 'padding="sometimes"', 'interactive="sometimes"'] as $attributes) {
  $reader->XML('<Image src="' . $source . '" ' . $attributes . ' />');
  $reader->read();
  try {
    $imageParser->parse($reader, new SPTK\Core\Style());
    throw new LogicException('Invalid image options accepted: ' . $attributes);
  } catch (InvalidArgumentException|RuntimeException $error) {
  }
}
$configured = new Image($source, zoom: 2, x: 10, y: -5);
$configuredViewport = new Tile(0, 0, 48, 32);
$originalView = $configured->destination($configuredViewport);
$configured->handleInput(imageKey(SDL::KEY_PLUS));
$configured->handleInput(imageKey(SDL::KEY_RIGHT));
$configured->handleInput(imageKey(SDL::KEY_EQUALS));
$configured->handleInput(imageKey(SDL::KEY_SPACE));
expectImage($configured->destination($configuredViewport) == $originalView, true, 'Space restores configured zoom and offsets after fitting');
$configured->handleInput(imageKey(SDL::KEY_MINUS));
$configured->handleInput(imageKey(SDL::KEY_SPACE));
expectImage($configured->destination($configuredViewport) == $originalView, true, 'Space repeatedly restores initial settings');
$fitted = new Image($source, fill: true);
$fitted->destination($configuredViewport);
$fitted->handleInput(imageKey(SDL::KEY_PLUS));
$fitted->handleInput(imageKey(SDL::KEY_RIGHT));
$fitted->handleInput(imageKey(SDL::KEY_SPACE));
expectImage($fitted->destination($configuredViewport)->width, 48, 'Space restores initial fill mode');
expectImage($fitted->destination(new Tile(0, 0, 24, 16))->width, 24, 'restored fill follows viewport resizing');
/** Count source replacement notifications. */
function imageReplaced(): void {
  global $imageReplacementCount;
  $imageReplacementCount++;
}

$imageReplacementCount = 0;
$replacementSource = imagecreatetruecolor(24, 12);
$replacementView = new Image($source, zoom: 2, x: 10, y: -5);
$replacementView->on('change', 'imageReplaced');
$replacementView->destination($configuredViewport);
$replacementView->handleInput(imageKey(SDL::KEY_EQUALS));
$replacementView->setSource($source);
expectImage($replacementView->destination($configuredViewport) == $originalView, true, 'replacement resets manual fitting and offsets');
$replacementView->setSource($replacementSource);
$rect = $replacementView->destination($configuredViewport);
expectImage([$rect->width, $rect->height, $rect->x, $rect->y], [48, 24, 0, 4], 'replacement restores zoom and centers a fitting source');
expectImage([$replacementView->source()->width, $replacementView->source()->height], [24, 12], 'replacement decodes new source');
expectImage($imageReplacementCount, 2, 'each source replacement notifies once');
expectImage([$image->source()->width, $image->source()->height], [96, 64], 'replacement preserves other shared sources');
imagedestroy($replacementSource);

$viewer = new Image($source);
$preferredWidth = $viewer->preferredWidth();
$viewport = new Tile(0, 0, 48, 32);
$viewer->destination($viewport);
$layout = new LayoutNode('vertical', '1*', '1*');
$layout->addLeaf(new LayoutLeaf('Image', '', '', $viewer));
$screen = new Screen($layout);
$screen->measureGrid(new Tile(0, 0, 8, 2));
$screen->handleEvent(imageKey(SDL::KEY_RIGHT));
expectImage($viewer->destination($viewport)->x, -24, 'image ignores input before activation');
$screen->handleEvent(imageKey(SDL::KEY_RETURN));
$screen->handleEvent(imageKey(SDL::KEY_RIGHT));
expectImage($viewer->destination($viewport)->x, -48, 'right pans half viewport');
$screen->handleEvent(imageKey(SDL::KEY_HOME));
expectImage($viewer->destination($viewport)->x, 0, 'Home shows left edge');
$screen->handleEvent(imageKey(SDL::KEY_END));
expectImage($viewer->destination($viewport)->x, -48, 'End shows right edge');
$screen->handleEvent(imageKey(SDL::KEY_LEFT));
expectImage($viewer->destination($viewport)->x, -24, 'left pans toward image start');
$screen->handleEvent(imageKey(SDL::KEY_UP));
expectImage($viewer->destination($viewport)->y, 0, 'up pans half viewport');
$screen->handleEvent(imageKey(SDL::KEY_PAGEDOWN));
expectImage($viewer->destination($viewport)->y, -32, 'Page Down shows bottom edge');
$screen->handleEvent(imageKey(SDL::KEY_DOWN));
expectImage($viewer->destination($viewport)->y, -32, 'down stops at bottom edge');
$screen->handleEvent(imageKey(SDL::KEY_PAGEUP));
expectImage($viewer->destination($viewport)->y, 0, 'Page Up shows top edge');
$screen->handleEvent(imageKey(SDL::KEY_SPACE));
$rect = $viewer->destination($viewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [-24, -16, 96, 64], 'Space restores original view');
$screen->handleEvent(imageKey(SDL::KEY_EQUALS));
$rect = $viewer->destination($viewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [0, 0, 48, 32], 'equals fits oversized image');
expectImage($viewer->preferredWidth(), $preferredWidth, 'fill input keeps layout preference');
$screen->handleEvent(imageKey(SDL::KEY_PLUS));
$rect = $viewer->destination($viewport);
expectImage([$rect->x, $rect->y, $rect->width, $rect->height], [-6, -4, 60, 40], 'plus zooms from fitted size');
$screen->handleEvent(imageKey(SDL::KEY_MINUS));
expectImage($viewer->destination($viewport)->width, 48, 'minus zooms out');
$screen->handleEvent(imageKey(SDL::KEY_ASTERISK));
expectImage($viewer->destination($viewport)->width, 60, 'asterisk zooms in');
$screen->handleEvent(imageKey(SDL::KEY_SLASH));
expectImage($viewer->destination($viewport)->width, 48, 'slash zooms out');
$screen->handleEvent(imageKey(SDL::KEY_KP_PLUS));
expectImage($viewer->destination($viewport)->width, 60, 'keypad plus zooms in');
$screen->handleEvent(imageKey(SDL::KEY_KP_DIVIDE));
expectImage($viewer->destination($viewport)->width, 48, 'keypad divide zooms out');
$screen->handleEvent(imageKey(SDL::KEY_EQUALS, SDL::MOD_SHIFT));
expectImage($viewer->destination($viewport)->width, 60, 'Shift+equals zooms in');
$screen->handleEvent(imageKey(SDL::KEY_KP_MINUS));
expectImage($viewer->destination($viewport)->width, 48, 'keypad minus zooms out');
$screen->handleEvent(imageKey(SDL::KEY_KP_MULTIPLY));
expectImage($viewer->destination($viewport)->width, 60, 'keypad multiply zooms in');
$screen->handleEvent(imageKey(SDL::KEY_KP_EQUALS));
expectImage($viewer->destination($viewport)->width, 48, 'keypad equals fits image');
$screen->handleEvent(imageKey(SDL::KEY_ESCAPE));
$screen->handleEvent(imageKey(SDL::KEY_PLUS));
expectImage($viewer->destination($viewport)->width, 48, 'image ignores input after release');
$gd = imagecreatetruecolor(2, 1);
imagealphablending($gd, false);
imagesavealpha($gd, true);
imagesetpixel($gd, 0, 0, imagecolorallocatealpha($gd, 255, 0, 0, 0));
imagesetpixel($gd, 1, 0, imagecolorallocatealpha($gd, 0, 0, 0, 127));
$memory = new Image($gd);
imagedestroy($gd);
expectImage($memory->source()->src, null, 'caller-owned GD source');
expectImage(strlen($memory->source()->pixels), 8, 'RGBA byte count');
$parser = new SPTK\XmlParser\XmlParser();
$leaves = $parser->windows[0]['screens'][3]->layout->leaves();
$images = [];
foreach ($leaves as $leaf) {
  if ($leaf->instance() instanceof Image) {
    $images[] = $leaf->instance();
  }
}
expectImage(count($images), 3, 'Image screen widget count');
$demoSource = (new ReflectionProperty(Image::class, 'image'))->getValue($images[0]);
expectImage($rasterProperty->getValue($demoSource), null, 'parsing all screens leaves demo pixels unloaded');
foreach ($images as $demoImage) {
  expectImage((new ReflectionProperty(Image::class, 'image'))->getValue($demoImage) === $demoSource, true, 'demo widgets share lazy source');
}
$missingPath = $source . '-missing';
$missing = new Image($missingPath);
try {
  $missing->source();
  throw new RuntimeException('Missing lazy image accepted.');
} catch (RuntimeException $error) {
  expectImage(str_contains($error->getMessage(), 'Cannot read image:'), true, 'missing file fails on first source access');
}
$temporarySource = ImageSource::from($source . '-unused');
$weak = WeakReference::create($temporarySource);
unset($temporarySource);
expectImage($weak->get(), null, 'cache does not retain unused image sources');
putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
expectImage($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), true, 'dummy SDL initialization');
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
$window = $sdl->ffi->SDL_CreateWindow('Image test', 200, 100, SDL::SDL_WINDOW_HIDDEN);
if ($window === null) {
  throw new RuntimeException('Cannot create dummy SDL window: ' . $sdl->error());
}
$nativeRenderer = $sdl->ffi->SDL_CreateRenderer($window, null);
if ($nativeRenderer === null) {
  throw new RuntimeException('Cannot create dummy SDL renderer: ' . $sdl->error());
}
try {
  $renderer = new PixelRenderer($nativeRenderer);
  $layoutKeys = new Image($source, zoom: 2);
  $layoutKeys->destination(new Tile(0, 0, 48, 32));
  $layoutKeys->handleInput(imageSymbolKey(SDL::KEY_EQUALS));
  expectImage($layoutKeys->destination(new Tile(0, 0, 48, 32))->width, 48, 'native equals scancode fits');
  $layoutKeys->handleInput(imageSymbolKey(SDL::KEY_PLUS));
  expectImage($layoutKeys->destination(new Tile(0, 0, 48, 32))->width, 60, 'native plus symbol zooms in');
  $layoutKeys->handleInput(imageSymbolKey(SDL::KEY_ASTERISK));
  expectImage($layoutKeys->destination(new Tile(0, 0, 48, 32))->width, 75, 'shifted number resolves to asterisk zoom');
  $layoutKeys->handleInput(imageSymbolKey(SDL::KEY_EQUALS));
  expectImage($layoutKeys->destination(new Tile(0, 0, 48, 32))->width, 48, 'layout symbol takes precedence over base event keycode');
  $missing->paintPixels($renderer, new Tile(0, 0, 0, 100), true);
  expectImage($rasterProperty->getValue(ImageSource::from($missingPath)), null, 'empty pixel tile does not decode');
  $renderer->fill(new Tile(0, 0, 12, 12), new Color(255, 0, 0));
  $renderer->fill(new Tile(12, 0, 12, 12), new Color(255, 0, 0));
  $renderer->fill(new Tile(24, 0, 12, 12), new Color(0, 255, 0));
  expectImage(imagePixel($sdl, $nativeRenderer, 6, 6), 0xff0000ff, 'first repeated draw color');
  expectImage(imagePixel($sdl, $nativeRenderer, 18, 6), 0xff0000ff, 'cached repeated draw color');
  expectImage(imagePixel($sdl, $nativeRenderer, 30, 6), 0x00ff00ff, 'changed draw color');
  $renderer->fill(new Tile(0, 0, 200, 100), new Color(10, 20, 30));
  $renderer->beginImages();
  $image->paintPixels($renderer, new Tile(0, 0, 200, 100), true);
  $renderer->endImages();
  expectImage(count((new ReflectionProperty(PixelRenderer::class, 'images'))->getValue($renderer)), 1, 'texture cached');
  $alias->paintPixels($renderer, new Tile(0, 0, 200, 100), false);
  expectImage(count((new ReflectionProperty(PixelRenderer::class, 'images'))->getValue($renderer)), 1, 'same file shares one SDL texture');
  $image->paintPixels($renderer, new Tile(0, 0, 200, 100), true);
  expectImage(imagePixel($sdl, $nativeRenderer, 52, 18), 0x0a141eff, 'transparent source reveals background');
  $bright = imagePixel($sdl, $nativeRenderer, 100, 50);
  expectImage($bright !== 0x0a141eff, true, 'image color reaches framebuffer');
  $renderer->fill(new Tile(0, 0, 200, 100), new Color(10, 20, 30));
  $renderer->image($image->source(), $image->destination(new Tile(0, 0, 200, 100)), new Tile(0, 0, 200, 100), false);
  $dim = imagePixel($sdl, $nativeRenderer, 100, 50);
  expectImage(($dim >> 24) < ($bright >> 24), true, 'unselected image dims');
  $renderer->fill(new Tile(0, 0, 200, 100), new Color(10, 20, 30));
  $clipped = new Image($source, zoom: 4);
  $clipped->paintPixels($renderer, new Tile(50, 20, 50, 30), true);
  expectImage(imagePixel($sdl, $nativeRenderer, 49, 35), 0x0a141eff, 'zoomed image is clipped outside tile');
  expectImage(imagePixel($sdl, $nativeRenderer, 75, 35) !== 0x0a141eff, true, 'zoomed image paints inside tile');
  $opaque = imagecreatetruecolor(6, 4);
  imagefill($opaque, 0, 0, imagecolorallocate($opaque, 200, 100, 50));
  $edgeCover = new Image($opaque, fit: 'cover', padding: false, interactive: false);
  imagedestroy($opaque);
  $renderer->fill(new Tile(0, 0, 200, 100), new Color(10, 20, 30));
  $edgeCover->paintPixels($renderer, new Tile(50, 20, 30, 10), true);
  foreach ([[50, 20], [79, 20], [50, 29], [79, 29]] as [$x, $y]) {
    expectImage(imagePixel($sdl, $nativeRenderer, $x, $y), 0xc86432ff, 'cover paints each corner without letterboxing');
  }
  expectImage(imagePixel($sdl, $nativeRenderer, 49, 20), 0x0a141eff, 'cropped cover preserves neighboring pixels');
  $pixelLeaf = new LayoutLeaf('Image', '', '', $edgeCover);
  $pixelLeaf->setGrid(new Tile(0, 0, 10, 4));
  $pixelGeometry = new SPTK\Layout\WindowGeometry(8, 15, 96, 75, 8, 7);
  $pixelLeaf->measureArea(new Tile(0, 0, 10, 4), $pixelGeometry);
  $renderer->fill(new Tile(0, 0, 200, 100), new Color(10, 20, 30));
  $pixelLeaf->paintPixels($renderer, $pixelGeometry, true);
  foreach ([[0, 0], [95, 0], [0, 74], [95, 74]] as [$x, $y]) {
    expectImage(imagePixel($sdl, $nativeRenderer, $x, $y), 0xc86432ff, 'unpadded image covers window-edge margins');
  }
  expectImage(imagePixel($sdl, $nativeRenderer, 96, 0), 0x0a141eff, 'unpadded image clips at its measured tile edge');
  $sdl->checkReturnValue($sdl->ffi->SDL_RenderPresent($nativeRenderer), 'SDL_RenderPresent');
  $renderer->beginImages();
  $renderer->endImages();
  expectImage(count((new ReflectionProperty(PixelRenderer::class, 'images'))->getValue($renderer)), 0, 'unused texture released');
  $renderer->close();
  $ttf = new TTF();
  expectImage($ttf->ffi->TTF_Init(), true, 'dummy TTF initialization');
  $font = new Font($ttf);
  $font->open('LiberationMono-Bold', 17);
  $atlas = new GlyphAtlas($sdl, $nativeRenderer, $font, 2);
  $first = $atlas->map('A', 1);
  $firstX = $first->x;
  $firstY = $first->y;
  $atlas->map('B', 1);
  $cached = $atlas->map('A', 1);
  expectImage([$cached->x, $cached->y], [$firstX, $firstY], 'atlas cache hit keeps glyph slot');
  $atlas->map('C', 1);
  $atlas->map('D', 1);
  $atlas->map('E', 1);
  $reloaded = $atlas->map('A', 1);
  expectImage($reloaded->x > $firstX, true, 'atlas slot reused after capacity reset');
  $cellWidth = $font->cellWidth();
  $cellHeight = $font->cellHeight();
  $renderer->fill(new Tile(0, 0, $cellWidth, $cellHeight), new Color(10, 20, 30));
  $sourceRect = $atlas->map('─', 1);
  $destinationRect = $sdl->ffi->new('SDL_FRect');
  $destinationRect->x = 0;
  $destinationRect->y = 0;
  $destinationRect->w = $cellWidth;
  $destinationRect->h = $cellHeight;
  $sdl->checkReturnValue($sdl->ffi->SDL_SetTextureColorMod($atlas->texture(), 255, 0, 0), 'SDL_SetTextureColorMod');
  $sdl->checkReturnValue($sdl->ffi->SDL_RenderTexture($nativeRenderer, $atlas->texture(), \FFI::addr($sourceRect), \FFI::addr($destinationRect)), 'SDL_RenderTexture');
  expectImage(imagePixel($sdl, $nativeRenderer, intdiv($cellWidth, 2), intdiv($cellHeight, 2)), 0xff0000ff, 'geometry glyph comes from tinted atlas');
  expectImage(imagePixel($sdl, $nativeRenderer, 0, 0), 0x0a141eff, 'geometry atlas keeps transparent pixels');
  $atlas->close();
  (new ReflectionProperty(SPTK\App::class, 'font'))->setValue($app, $font);
  $definition = $parser->windows[0];
  $definition['state'] = 'hidden';
  $initialWindow = new Window($definition);
  try {
    expectImage($rasterProperty->getValue($demoSource), null, 'window startup does not decode unopened image screen');
  } finally {
    $initialWindow->close();
  }
  $definition['screens'] = [$parser->windows[0]['screens'][3]];
  $windowWidget = new Window($definition);
  expectImage($images[0]->source() === $images[1]->source() && $images[1]->source() === $images[2]->source(), true, 'visible demo screen decodes one shared raster');
  $windowWidget->close();
  $interactiveImage = new Image($source, zoom: 2);
  $layout = new LayoutNode('horizontal', '1*', '1*');
  $layout->addLeaf(new LayoutLeaf('Image', '', '', $interactiveImage));
  $layout->addLeaf(new LayoutLeaf('Text', '', '', new Text('Aside')));
  $definition['screens'] = [new Screen($layout)];
  $definition['width'] = 24;
  $definition['height'] = 8;
  $interactiveWindow = new Window($definition);
  try {
    $geometryProperty = new ReflectionProperty(Window::class, 'geometry');
    $initialGeometry = $geometryProperty->getValue($interactiveWindow);
    $nativeWindow = (new ReflectionProperty(Window::class, 'window'))->getValue($interactiveWindow);
    $sdl->checkReturnValue($sdl->ffi->SDL_SetWindowSize($nativeWindow, $initialGeometry->windowWidth + 5, $initialGeometry->windowHeight + 7), 'SDL_SetWindowSize');
    $sdl->checkReturnValue($sdl->ffi->SDL_SyncWindow($nativeWindow), 'SDL_SyncWindow');
    $interactiveWindow->resize();
    $resizedGeometry = $geometryProperty->getValue($interactiveWindow);
    expectImage($resizedGeometry !== $initialGeometry, true, 'resize replaces the window geometry');
    expectImage([$resizedGeometry->windowWidth, $resizedGeometry->windowHeight], [$initialGeometry->windowWidth + 5, $initialGeometry->windowHeight + 7], 'geometry captures resized window dimensions');
    $resizedGrid = (new ReflectionProperty(Window::class, 'grid'))->getValue($interactiveWindow);
    expectImage($resizedGeometry->offsetX, intdiv($resizedGeometry->windowWidth - $resizedGrid->width() * $font->cellWidth(), 2), 'resized geometry centers the grid horizontally');
    expectImage($resizedGeometry->offsetY, intdiv($resizedGeometry->windowHeight - $resizedGrid->height() * $font->cellHeight(), 2), 'resized geometry centers the grid vertically');
    $interactiveWindow->handleEvent(imageKey(SDL::KEY_RIGHT));
    expectImage($definition['screens'][0]->selectedLeaf()?->instance() instanceof Text, true, 'focus redraw selects second tile');
    $interactiveWindow->handleEvent(imageKey(SDL::KEY_LEFT));
    expectImage($definition['screens'][0]->selectedLeaf()?->instance() instanceof Image, true, 'focus redraw returns to image');
    $interactiveWindow->handleEvent(imageKey(SDL::KEY_RETURN));
    $interactiveWindow->handleEvent(imageKey(SDL::KEY_RIGHT));
    expectImage((new ReflectionProperty(Image::class, 'x'))->getValue($interactiveImage) < 0, true, 'pixel widget redraw after input');
    $interactiveWindow->handleEvent(imageSymbolKey(SDL::KEY_EQUALS));
    expectImage((new ReflectionProperty(Image::class, 'fill'))->getValue($interactiveImage), true, 'window input fits using actual layout equals');
    $interactiveWindow->handleEvent(imageKey(SDL::KEY_SPACE));
    expectImage((new ReflectionProperty(Image::class, 'zoom'))->getValue($interactiveImage), 2.0, 'window input restores configured zoom');
  } finally {
    $interactiveWindow->close();
  }
  $staticLayout = new LayoutNode('horizontal', '1*', '1*');
  $staticLayout->addLeaf(new LayoutLeaf('Image', '1*', '', $edgeCover));
  $staticSeparator = new SPTK\Layout\LayoutSeparator();
  $staticLayout->addSeparator($staticSeparator);
  $staticLayout->addLeaf(new LayoutLeaf('Text', '1*', '', new Text('Aside')));
  $staticScreen = new Screen($staticLayout);
  $definition['screens'] = [$staticScreen];
  $staticWindow = new Window($definition);
  try {
    $separatorArea = (new ReflectionProperty($staticSeparator, 'area'))->getValue($staticSeparator);
    expectImage(windowImagePixel($sdl, $staticWindow, 0, 0), 0xc86432ff, 'static cover fills outer window padding');
    expectImage(windowImagePixel($sdl, $staticWindow, $separatorArea->x, 0), 0x475568ff, 'separator remains over unpadded cover in complete frame');
    $staticWindow->handleEvent(imageKey(SDL::KEY_RETURN));
    expectImage($staticScreen->activeLeaf(), null, 'display-only cover cannot enter input mode in a native window');
    $staticWindow->handleEvent(imageKey(SDL::KEY_RIGHT));
    expectImage($staticScreen->selectedLeaf()->instance() instanceof Text, true, 'static image arrows select adjacent widget');
    expectImage(windowImagePixel($sdl, $staticWindow, $separatorArea->x, 0), 0x475568ff, 'separator remains after focus repaint');
  } finally {
    $staticWindow->close();
  }
  $font->close();
  $ttf->close();
} finally {
  $sdl->ffi->SDL_DestroyRenderer($nativeRenderer);
  $sdl->ffi->SDL_DestroyWindow($window);
  $sdl->close();
}
echo "Image checks passed\n";
