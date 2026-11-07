<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Style, Window};
use SPTK\Events\{EventContext, EventDefinition, EventLoop};
use SPTK\Layout\{LayoutLeaf, LayoutNode};
use SPTK\Rendering\Font;
use SPTK\SDLWrapper\{SDL, TTF};
use SPTK\Widgets\Button\Button;
use SPTK\Widgets\Canvas\Canvas;
use SPTK\Widgets\Input\Input;
use SPTK\Widgets\List\ListView;

/** Count button presses and keyboard shortcuts independently. */
final class MouseAction {

  public static int $buttons = 0;
  public static int $shortcuts = 0;
  public static int $accepted = 0;

  public static function button(EventContext $event): void {
    self::$buttons++;
  }

  public static function shortcut(EventContext $event): bool {
    self::$shortcuts++;
    return true;
  }

  public static function accept(EventContext $event): void {
    self::$accepted++;
  }

}

/** Count raw keys sent to a custom canvas. */
final class MouseCanvas extends Canvas {

  public int $keys = 0;

  public function handleInput(mixed $event): bool {
    $this->keys++;
    return true;
  }

}

/** Send a native SDL mouse button event through the event loop. */
function mouseButton(EventLoop $loop, SDL $sdl, Window $window, int $type, int $x, int $y, int $button = SDL::SDL_BUTTON_LEFT, int $clicks = 1): void {
  $event = $sdl->ffi->new('SDL_Event');
  $event->button->type = $type;
  $event->button->windowID = $window->id();
  $event->button->button = $button;
  $event->button->clicks = $clicks;
  $event->button->x = $x;
  $event->button->y = $y;
  (new ReflectionMethod(EventLoop::class, 'handleSdlEvent'))->invoke($loop, $event);
}

/** Send a native SDL wheel event through the event loop. */
function mouseWheel(EventLoop $loop, SDL $sdl, Window $window, int $x, int $y, float $steps, int $direction = 0): void {
  $event = $sdl->ffi->new('SDL_Event');
  $event->wheel->type = SDL::SDL_EVENT_MOUSE_WHEEL;
  $event->wheel->windowID = $window->id();
  $event->wheel->mouse_x = $x;
  $event->wheel->mouse_y = $y;
  $event->wheel->y = $steps;
  $event->wheel->direction = $direction;
  (new ReflectionMethod(EventLoop::class, 'handleSdlEvent'))->invoke($loop, $event);
}

/** Return a visible cell near the middle of one leaf. */
function mousePoint(Window $window, LayoutLeaf $leaf): array {
  $geometry = (new ReflectionProperty(Window::class, 'geometry'))->getValue($window);
  $area = $leaf->pixelTile() ?? $geometry->pixelArea($leaf->visibleGrid());
  return [$area->x + intdiv($area->width, 2), $area->y + intdiv($area->height, 2)];
}

/** Complete one left click at a leaf's visible center. */
function mouseClick(EventLoop $loop, SDL $sdl, Window $window, LayoutLeaf $leaf): void {
  [$x, $y] = mousePoint($window, $leaf);
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_DOWN, $x, $y);
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_UP, $x, $y);
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
$first = new Input('First');
$second = new Input('Second');
$list = new ListView(array_map('strval', range(0, 12)), filterable: false, searchable: false);
$canvas = new MouseCanvas();
$firstLeaf = new LayoutLeaf('Input', '1*', '2', $first,
  [new EventDefinition('accept', null, MouseAction::class . '::accept')]);
$secondLeaf = new LayoutLeaf('Input', '1*', '2', $second);
$buttonLeaf = new LayoutLeaf('Button', '1*', '1', new Button('Run', null, MouseAction::class . '::button', new Style()));
$listLeaf = new LayoutLeaf('List', '1*', '4', $list);
$canvasLeaf = new LayoutLeaf('Canvas', '1*', '2', $canvas);
$layout = new LayoutNode('vertical', '1*', '1*');
foreach ([$firstLeaf, $secondLeaf, $buttonLeaf, $listLeaf, $canvasLeaf] as $leaf) {
  $layout->addLeaf($leaf);
}
$screen = new Screen($layout, events: [new EventDefinition('keyDown', 'down', MouseAction::class . '::shortcut')]);
$window = new Window(['title' => 'Mouse input', 'width' => 42, 'height' => 17,
  'state' => 'hidden', 'resizable' => false, 'screens' => [$screen]]);
$loop = new EventLoop();
$loop->registerWindow($window);
$groupWindow = null;
try {
  [$firstX, $firstY] = mousePoint($window, $firstLeaf);
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_DOWN, $firstX, $firstY);
  if ($screen->activeLeaf() !== null) {
    throw new RuntimeException('Mouse down must not activate a tile.');
  }
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_UP, $firstX, $firstY);
  if ($screen->activeLeaf() !== $firstLeaf || !$first->editing()) {
    throw new RuntimeException('Mouse release must activate the clicked input.');
  }
  mouseClick($loop, $sdl, $window, $secondLeaf);
  if ($screen->activeLeaf() !== $secondLeaf || $first->editing() || !$second->editing() || MouseAction::$accepted !== 1) {
    throw new RuntimeException('Clicking another tile must finish the old widget and activate the new one.');
  }
  [$buttonX, $buttonY] = mousePoint($window, $buttonLeaf);
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_DOWN, $buttonX, $buttonY);
  mouseButton($loop, $sdl, $window, SDL::SDL_EVENT_MOUSE_BUTTON_UP, $buttonX, $firstY);
  if (MouseAction::$buttons !== 0 || $screen->activeLeaf() !== $secondLeaf) {
    throw new RuntimeException('A release outside the pressed tile must not run its button.');
  }
  mouseClick($loop, $sdl, $window, $buttonLeaf);
  if (MouseAction::$buttons !== 1 || $screen->activeLeaf() !== null || $screen->selectedLeaf() !== $buttonLeaf) {
    throw new RuntimeException('Button clicks must run once and remain in layout mode.');
  }
  mouseClick($loop, $sdl, $window, $listLeaf);
  [$listX, $listY] = mousePoint($window, $listLeaf);
  mouseWheel($loop, $sdl, $window, $firstX, $firstY, -1.0);
  mouseWheel($loop, $sdl, $window, $listX, $listY, -0.5);
  if ($list->cursorPosition() !== 0) {
    throw new RuntimeException('Wheel input outside the active tile or below one step must not move its cursor.');
  }
  mouseWheel($loop, $sdl, $window, $listX, $listY, -0.5);
  if ($list->cursorPosition() !== 1 || MouseAction::$shortcuts !== 0) {
    throw new RuntimeException('Wheel movement must use the active widget without firing screen shortcuts.');
  }
  mouseWheel($loop, $sdl, $window, $listX, $listY, -1.0, SDL::SDL_MOUSEWHEEL_FLIPPED);
  if ($list->cursorPosition() !== 0) {
    throw new RuntimeException('Flipped wheel direction must reverse its movement.');
  }
  mouseClick($loop, $sdl, $window, $canvasLeaf);
  [$canvasX, $canvasY] = mousePoint($window, $canvasLeaf);
  mouseWheel($loop, $sdl, $window, $canvasX, $canvasY, -1.0);
  if ($canvas->keys !== 0) {
    throw new RuntimeException('Wheel input must not become a key in a custom canvas.');
  }
  $groupFirst = new LayoutLeaf('Input', '1*', '2', new Input('Group first'));
  $groupSecond = new LayoutLeaf('Input', '1*', '2', new Input('Group second'));
  $group = new LayoutNode('vertical', '1*', '1*', false, enterChildren: true);
  $group->addLeaf($groupFirst);
  $group->addLeaf($groupSecond);
  $groupRoot = new LayoutNode('vertical', '1*', '1*');
  $groupRoot->addLeaf(new LayoutLeaf('Input', '1*', '2', new Input('Outside')));
  $groupRoot->addNode($group);
  $groupScreen = new Screen($groupRoot);
  $groupWindow = new Window(['title' => 'Mouse group', 'width' => 32, 'height' => 9,
    'state' => 'hidden', 'resizable' => false, 'screens' => [$groupScreen]]);
  $loop->registerWindow($groupWindow);
  mouseClick($loop, $sdl, $groupWindow, $groupSecond);
  if ($groupScreen->activeLeaf() !== $groupSecond || $groupScreen->selectedLeaf() !== $groupSecond) {
    throw new RuntimeException('Clicking a grouped child must enter the group and activate that child.');
  }
} finally {
  $groupWindow?->close();
  $window->close();
  $font->close();
  $ttf->close();
  $sdl->close();
}
echo "Mouse input checks passed\n";
