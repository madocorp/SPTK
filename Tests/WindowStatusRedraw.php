<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Window};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\Widgets\Canvas\Canvas;
use SPTK\Widgets\StatusBar\StatusBar;

class StatusChangingCanvas extends Canvas {
  public ?StatusBar $status = null;

  public function handleInput(mixed $event): bool {
    if ($event->type !== SDL::SDL_EVENT_TEXT_INPUT) {
      return false;
    }
    $this->status->notice('Invalid glissando', 'continuous');
    return true;
  }
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

$canvas = new StatusChangingCanvas();
$status = new StatusBar();
$canvas->status = $status;
$canvasLeaf = new LayoutLeaf('Canvas', '1*', '1*', $canvas);
$statusLeaf = new LayoutLeaf('StatusBar', '1*', '1', $status, navigate: false);
$layout = new LayoutNode('vertical', '1*', '1*');
$layout->addLeaf($canvasLeaf);
$layout->addLeaf($statusLeaf);
$screen = new Screen($layout);
$window = new Window([
  'title' => 'Status redraw test',
  'width' => 36,
  'height' => 10,
  'state' => 'hidden',
  'resizable' => false,
  'screens' => [$screen],
]);
try {
  $screen->activateLeaf($canvasLeaf);
  if (!$window->handleEvent((object)['type' => SDL::SDL_EVENT_TEXT_INPUT])) {
    throw new RuntimeException('Canvas input was not handled.');
  }
  $grid = (new ReflectionProperty(Window::class, 'grid'))->getValue($window);
  $row = $statusLeaf->grid()->y;
  foreach (str_split('Invalid glissando') as $x => $letter) {
    if ($grid->cell($x, $row)->glyph !== $letter) {
      throw new RuntimeException('Status message was not painted with the input event.');
    }
  }
} finally {
  $window->close();
  $font->close();
  $ttf->close();
  $sdl->close();
}
echo "Window status redraw checks passed\n";
