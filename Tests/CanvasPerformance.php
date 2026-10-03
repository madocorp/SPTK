<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
require_once APP_DIR . '/../Demo/Controller.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Texture, TextureContext, Window};
use SPTK\Events\{EventDefinition, KeyboardEvent};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\Widgets\Canvas\Canvas;

/** Count paints while using the demo's tiled sprite scene. */
function performancePaint(Texture $surface, TextureContext $textures): void {
  global $paintCount;
  $paintCount++;
  Controller::paintCanvas($surface, $textures);
}

/** Build a synthetic repeated key event for the normal window input path. */
function performanceKey(SDL $sdl, int $key, bool $repeat = false): KeyboardEvent {
  $event = $sdl->ffi->new('SDL_Event');
  $event->type = SDL::SDL_EVENT_KEY_DOWN;
  $event->key->key = $key;
  $event->key->repeat = $repeat;
  return new KeyboardEvent($event);
}

/** Drain queued expose notifications through the real window redraw path. */
function performanceDrain(SDL $sdl, Window $window): int {
  $event = $sdl->ffi->new('SDL_Event');
  $redraws = 0;
  while ($sdl->ffi->SDL_PollEvent(FFI::addr($event))) {
    if ($event->type === SDL::SDL_EVENT_WINDOW_EXPOSED && $event->window->windowID === $window->id()) {
      $redraws += (int)$window->handleEvent($event);
    }
  }
  return $redraws;
}

if (getenv('SDL_VIDEODRIVER') === false) {
  putenv('SDL_VIDEODRIVER=dummy');
}
$sdl = new SDL();
$sdl->checkReturnValue($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), 'SDL_Init');
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
$ttf = new TTF();
$ttf->ffi->TTF_Init();
$font = new Font($ttf);
$font->open('LiberationMono-Bold', 17);
(new ReflectionProperty(SPTK\App::class, 'font'))->setValue($app, $font);
$paintCount = 0;
$columns = (int)($argv[1] ?? 100);
$rows = (int)($argv[2] ?? 30);
$canvas = new Canvas('performancePaint');
$layout = new LayoutNode('vertical', '1*', '1*');
$layout->addLeaf(new LayoutLeaf('Canvas', '', '', $canvas, [new EventDefinition('keyDown', 'space', 'Controller::advanceCanvas')]));
$window = new Window(['title' => 'Canvas performance', 'width' => $columns, 'height' => $rows, 'state' => 'hidden', 'screens' => [new Screen($layout)]]);
try {
  $window->handleEvent(performanceKey($sdl, SDL::KEY_RETURN));
  performanceDrain($sdl, $window);
  $paintsBefore = $paintCount;
  $revisionBefore = $canvas->revision();
  $times = [];
  $queuedRedraws = 0;
  for ($i = 0; $i < 50; $i++) {
    $start = hrtime(true);
    $window->handleEvent(performanceKey($sdl, SDL::KEY_SPACE, $i > 0));
    $textHandled = $window->handleEvent((object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => ' ']]);
    if ($textHandled) {
      throw new RuntimeException('Unconsumed Space text must not redraw the window.');
    }
    $queuedRedraws += performanceDrain($sdl, $window);
    $times[] = (hrtime(true) - $start) / 1000000;
  }
  if ($canvas->revision() - $revisionBefore !== 50 || $paintCount - $paintsBefore !== 50) {
    throw new RuntimeException('Every repeat must update and paint the scene.');
  }
  if ($queuedRedraws !== 0) {
    throw new RuntimeException('Already painted keyboard changes must not trigger extra expose redraws.');
  }
  $paintsBefore = $paintCount;
  $canvas->invalidate();
  if (performanceDrain($sdl, $window) !== 1 || $paintCount !== $paintsBefore + 1) {
    throw new RuntimeException('Timer-style invalidation must still schedule a repaint without keyboard input.');
  }
  $expose = $sdl->ffi->new('SDL_Event');
  $expose->type = SDL::SDL_EVENT_WINDOW_EXPOSED;
  $expose->window->windowID = $window->id();
  if (!$window->handleEvent($expose)) {
    throw new RuntimeException('Native expose events must still redraw the window.');
  }
  sort($times);
  printf("%s, %dx%d pixels: median %.2f ms, max %.2f ms; 50 repeats, %d extra expose redraws\n", getenv('SDL_VIDEODRIVER'), $columns * $font->cellWidth(), $rows * $font->cellHeight(), $times[25], max($times), $queuedRedraws);
} finally {
  $window->close();
  $font->close();
  $ttf->ffi->TTF_Quit();
  $sdl->close();
}
