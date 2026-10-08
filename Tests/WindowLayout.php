<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Window};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\Widgets\Text\Text;

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
$first = new LayoutNode('vertical', '1*', '1*');
$first->addLeaf(new LayoutLeaf('Text', '1*', '1*', new Text('First')));
$second = new LayoutNode('vertical', '1*', '1*');
$old = new LayoutLeaf('Text', '1*', '1*', new Text('Old'));
$second->addLeaf($old);
$screen = new Screen($second, id: 'second');
$window = new Window([
  'title' => 'Window layout test',
  'width' => 32,
  'height' => 12,
  'state' => 'hidden',
  'resizable' => false,
  'screens' => [new Screen($first, id: 'first'), $screen],
]);
try {
  $replacement = new LayoutLeaf('Text', '1*', '1*', new Text('Replacement'));
  $second->replaceChild($old, $replacement);
  $screen->setLayout($second);
  $window->setCurrentScreen(1);
  if ((new ReflectionProperty($replacement, 'area'))->getValue($replacement) === null) {
    throw new RuntimeException('A changed hidden screen was not measured before drawing.');
  }
} finally {
  $window->close();
  $font->close();
  $ttf->close();
  $sdl->close();
}
echo "Window layout checks passed\n";
