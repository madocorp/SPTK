<?php

define('APP_DIR', __DIR__);

require APP_DIR . '/SPTK/App.php';

use SPTK\Core\Window;
use SPTK\Events\EventLoop;
use SPTK\SDLWrapper\SDL;

$windowClass = new ReflectionClass(Window::class);
$window = $windowClass->newInstanceWithoutConstructor();
$windowClass->getProperty('id')->setValue($window, 1);
$requests = 0;
$window->setCloseRequestHandler(function () use (&$requests): bool {
  $requests++;
  return false;
});
$loop = new EventLoop();
$loop->registerWindow($window);
$loopClass = new ReflectionClass(EventLoop::class);
$loopClass->getProperty('running')->setValue($loop, true);
$window->handleEvent((object)['type' => SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED]);
$loopClass->getMethod('handleSdlEvent')->invoke($loop, (object)['type' => SDL::SDL_QUIT]);
if ($requests !== 2 || $loopClass->getProperty('running')->getValue($loop) !== true) {
  throw new RuntimeException('A rejected quit request stopped the event loop.');
}
$window->setCloseRequestHandler(static fn(): bool => true);
$loopClass->getMethod('handleSdlEvent')->invoke($loop, (object)['type' => SDL::SDL_QUIT]);
if ($loopClass->getProperty('running')->getValue($loop) !== false) {
  throw new RuntimeException('An accepted quit request did not stop the event loop.');
}
echo "Quit request test passed\n";
