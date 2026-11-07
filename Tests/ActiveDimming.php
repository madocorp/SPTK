<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Window};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\Widgets\Canvas\Canvas;
use SPTK\Widgets\Input\Input;

/** Exercise a pixel widget's partial repaint while its tile is active. */
final class ActiveDimmingCanvas extends Canvas {

  public function handleInput(mixed $event): bool {
    $this->invalidate();
    return true;
  }

}

/** Read one pixel from the presented window image. */
function focusPixel(SDL $sdl, Window $window, int $x, int $y): int {
  $renderer = (new ReflectionProperty(Window::class, 'ffiRenderer'))->getValue($window);
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
    $pixels = FFI::cast('uint32_t*', $rgba->pixels);
    return $pixels[$y * intdiv($rgba->pitch, 4) + $x];
  } finally {
    $sdl->ffi->SDL_DestroySurface($rgba);
    $sdl->ffi->SDL_DestroySurface($surface);
  }
}

/** Send a normal key press through the window's rendering path. */
function focusKey(Window $window, int $key): void {
  $window->handleEvent((object)['type' => SDL::SDL_EVENT_KEY_DOWN,
    'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false]]);
}

putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
$sdl->checkReturnValue($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), 'SDL_Init');
$ttf = new TTF();
if (!$ttf->ffi->TTF_Init()) {
  throw new RuntimeException('TTF_Init failed.');
}
$font = new Font($ttf);
$font->open('LiberationMono-Bold', 17);
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
(new ReflectionProperty(SPTK\App::class, 'font'))->setValue($app, $font);
$first = new LayoutLeaf('Input', '1*', '2', new Input('First'));
$second = new LayoutLeaf('Input', '1*', '2', new Input('Second'));
$canvas = new LayoutLeaf('Canvas', '1*', '2', new ActiveDimmingCanvas());
$layout = new LayoutNode('vertical', '1*', '1*');
$layout->addLeaf($first);
$separator = new LayoutSeparator();
$layout->addSeparator($separator);
$layout->addLeaf($second);
$layout->addLeaf($canvas);
$screen = new Screen($layout);
$window = new Window(['title' => 'Active dimming', 'width' => 32, 'height' => 8,
  'state' => 'hidden', 'resizable' => false, 'screens' => [$screen]]);
try {
  $geometry = (new ReflectionProperty(Window::class, 'geometry'))->getValue($window);
  $firstArea = $geometry->pixelArea($first->visibleGrid());
  $secondArea = $geometry->pixelArea($second->visibleGrid());
  $canvasArea = $geometry->pixelArea($canvas->visibleGrid());
  $firstPixel = [$firstArea->x + $firstArea->width - 4, $firstArea->y + 4];
  $secondPixel = [$secondArea->x + $secondArea->width - 4, $secondArea->y + 4];
  $canvasPixel = [$canvasArea->x + $canvasArea->width - 4, $canvasArea->y + 4];
  $separatorArea = (new ReflectionProperty($separator, 'area'))->getValue($separator);
  $separatorPixels = [[$separatorArea->x + intdiv($separatorArea->width, 2), $separatorArea->y],
    [$separatorArea->x + intdiv($separatorArea->width, 2), $separatorArea->y + 1]];
  $activePaddingPixel = [$firstArea->x + intdiv($firstArea->width, 2), $firstArea->y + $firstArea->height + 2];
  $paddingPixel = [$secondArea->x + intdiv($secondArea->width, 2), $secondArea->y + $secondArea->height + 2];
  $selectedBefore = focusPixel($sdl, $window, ...$firstPixel);
  $activePaddingBefore = focusPixel($sdl, $window, ...$activePaddingPixel);
  $otherBefore = focusPixel($sdl, $window, ...$secondPixel);
  $canvasBefore = focusPixel($sdl, $window, ...$canvasPixel);
  $separatorBefore = [];
  foreach ($separatorPixels as $point) {
    $separatorBefore[] = focusPixel($sdl, $window, ...$point);
  }
  $paddingBefore = focusPixel($sdl, $window, ...$paddingPixel);
  focusKey($window, SDL::KEY_RETURN);
  if ($screen->activeLeaf() !== $first || focusPixel($sdl, $window, ...$firstPixel) !== $selectedBefore
    || focusPixel($sdl, $window, ...$activePaddingPixel) !== $activePaddingBefore
    || focusPixel($sdl, $window, ...$secondPixel) >= $otherBefore
    || focusPixel($sdl, $window, ...$canvasPixel) >= $canvasBefore
    || focusPixel($sdl, $window, ...$paddingPixel) >= $paddingBefore) {
    throw new RuntimeException('Activation must leave the selected tile unchanged and darken tiles and padding.');
  }
  foreach ($separatorPixels as $index => $point) {
    if (focusPixel($sdl, $window, ...$point) >= $separatorBefore[$index]) {
      throw new RuntimeException('Both separator pixels must darken with the rest of the window.');
    }
  }
  focusKey($window, SDL::KEY_RIGHT);
  if (focusPixel($sdl, $window, ...$paddingPixel) >= $paddingBefore
    || focusPixel($sdl, $window, ...$separatorPixels[0]) >= $separatorBefore[0]) {
    throw new RuntimeException('Input edits must keep the overlay over gaps and separators.');
  }
  focusKey($window, SDL::KEY_ESCAPE);
  if ($screen->activeLeaf() !== null || focusPixel($sdl, $window, ...$firstPixel) !== $selectedBefore
    || focusPixel($sdl, $window, ...$secondPixel) !== $otherBefore
    || focusPixel($sdl, $window, ...$canvasPixel) !== $canvasBefore
    || focusPixel($sdl, $window, ...$paddingPixel) !== $paddingBefore) {
    throw new RuntimeException('Releasing input must restore the normal tile brightness.');
  }
  foreach ($separatorPixels as $index => $point) {
    if (focusPixel($sdl, $window, ...$point) !== $separatorBefore[$index]) {
      throw new RuntimeException('Releasing input must restore separator brightness.');
    }
  }
  focusKey($window, SDL::KEY_DOWN);
  if ($screen->selectedLeaf() !== $second || focusPixel($sdl, $window, ...$secondPixel) === $otherBefore) {
    throw new RuntimeException('Normal selection must still move between tiles.');
  }
  focusKey($window, SDL::KEY_DOWN);
  focusKey($window, SDL::KEY_RETURN);
  focusKey($window, SDL::KEY_RIGHT);
  if ($screen->activeLeaf() !== $canvas || focusPixel($sdl, $window, ...$firstPixel) >= $selectedBefore
    || focusPixel($sdl, $window, ...$separatorPixels[0]) >= $separatorBefore[0]) {
    throw new RuntimeException('A pixel widget redraw must keep the window overlay.');
  }
} finally {
  $window->close();
  $font->close();
  $ttf->close();
  $sdl->close();
}
echo "Focus dimming checks passed\n";
