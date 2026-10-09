<?php

define('APP_DIR', dirname(__DIR__) . '/Demo');
require_once APP_DIR . '/SPTK/App.php';
require_once APP_DIR . '/Controller.php';

use SPTK\Core\{Style, Window};
use SPTK\Events\EventLoop;
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\XmlParser\ScreenParser;

/** Send a demo shortcut through the Text screen. */
function demoKey(\SPTK\Core\Screen $screen, int $key): void {
  $screen->handleEvent((object)[
    'type' => SDL::SDL_EVENT_KEY_DOWN,
    'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false],
  ]);
}

/** Require the Text demo to show the requested status state. */
function expectDemoStatus(\SPTK\Core\Screen $screen, string $style, string $behavior): void {
  if ($screen->statusBar?->kind() !== $style || $screen->statusBar->behavior() !== $behavior) {
    throw new RuntimeException("Expected {$style}/{$behavior} status in Text demo.");
  }
}

putenv('SDL_VIDEODRIVER=dummy');
$sdl = new SDL();
$sdl->checkReturnValue($sdl->ffi->SDL_Init(SDL::SDL_INIT_VIDEO), 'SDL_Init');
$ttf = new TTF();
$sdl->checkReturnValue($ttf->ffi->TTF_Init(), 'TTF_Init');
$font = new Font($ttf);
$font->open('LiberationMono-Bold', 17);
$app = (new ReflectionClass(SPTK\App::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(SPTK\App::class, 'instance'))->setValue(null, $app);
(new ReflectionProperty(SPTK\App::class, 'sdl'))->setValue($app, $sdl);
(new ReflectionProperty(SPTK\App::class, 'font'))->setValue($app, $font);
$loop = new EventLoop();
(new ReflectionProperty(SPTK\App::class, 'eventLoop'))->setValue($app, $loop);
$screen = (new ScreenParser())->parse('text.xml', new Style(), 'text', 'Text');
$window = new Window([
  'title' => 'Text status demo test', 'width' => 100, 'height' => 30,
  'state' => 'hidden', 'resizable' => false, 'screens' => [$screen],
]);
$loop->registerWindow($window);
try {
  if ($screen->widget('status') !== $screen->statusBar || $screen->statusBar->text() !== '') {
    throw new RuntimeException('Text demo must start with an empty StatusBar.');
  }
  demoKey($screen, ord('1'));
  expectDemoStatus($screen, 'info', 'modal');
  demoKey($screen, SDL::KEY_RETURN);
  demoKey($screen, ord('5'));
  expectDemoStatus($screen, 'notice', 'continuous');
  demoKey($screen, ord('2'));
  expectDemoStatus($screen, 'warning', 'modal');
  demoKey($screen, SDL::KEY_ESCAPE);
  expectDemoStatus($screen, 'notice', 'continuous');
  demoKey($screen, ord('3'));
  expectDemoStatus($screen, 'error', 'modal');
  demoKey($screen, SDL::KEY_RETURN);
  demoKey($screen, ord('4'));
  expectDemoStatus($screen, 'warning', 'confirmation');
  demoKey($screen, ord('n'));
  expectDemoStatus($screen, 'notice', 'background');
  demoKey($screen, ord('5'));
  demoKey($screen, ord('6'));
  expectDemoStatus($screen, 'info', 'background');
  $timers = (new ReflectionProperty($loop, 'timers'))->getValue($loop);
  foreach ($timers as &$timer) {
    $timer['deadline'] = hrtime(true) - 1;
  }
  unset($timer);
  (new ReflectionProperty($loop, 'timers'))->setValue($loop, $timers);
  (new ReflectionMethod($loop, 'dispatchDueTimers'))->invoke($loop);
  expectDemoStatus($screen, 'notice', 'continuous');
} finally {
  $window->close();
  $font->close();
  $ttf->close();
  $sdl->close();
}
echo "Text demo StatusBar checks passed\n";
