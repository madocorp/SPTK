<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Color, Screen, Style};
use SPTK\Events\EventContext;
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator, Tile};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Empty\Placeholder;

/** Stand-in for the demo controller used by overwrite.xml. */
final class Controller {

  public static array $chosen = [];

  /** Record whether the selector was already restored when its deactivate ran. */
  public static function overwriteFileChosen(EventContext $event): void {
    self::$chosen[] = $event->widget->id();
  }

}

/** Assert a named overwrite result. */
function expectOverwrite(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Assert that a call throws the given exception class. */
function expectOverwriteError(callable $call, string $class, string $name): void {
  try {
    $call();
  } catch (Throwable $error) {
    expectOverwrite($error instanceof $class, true, $name);
    return;
  }
  throw new RuntimeException($name . ': expected ' . $class);
}

/** Create one key press event. */
function overwriteKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0]];
}

/** Return the shown leaf holding a widget id, or null when it is hidden. */
function shownLeaf(Screen $screen, string $id): ?LayoutLeaf {
  foreach ($screen->layout->leaves() as $leaf) {
    if ($leaf->instance()->id() === $id) {
      return $leaf;
    }
  }
  return null;
}

/** Return the shown button leaf with a label prefix. */
function buttonLeaf(Screen $screen, string $label): LayoutLeaf {
  foreach ($screen->layout->leaves() as $leaf) {
    $widget = $leaf->instance();
    if ($widget instanceof SPTK\Widgets\Button\Button && str_starts_with($widget->tip(), 'Return: ' . $label)) {
      return $leaf;
    }
  }
  throw new RuntimeException("Button not shown: {$label}");
}

/** Build a placeholder leaf with an id and a fixed height. */
function overwritePlaceholder(string $id, string $height): LayoutLeaf {
  $widget = new Placeholder(new Color(0, 0, 0));
  $widget->setId($id);
  return new LayoutLeaf('Empty', '', $height, $widget);
}

$size = new Tile(0, 0, 60, 20);
$screen = (new SPTK\XmlParser\ScreenParser())->parse('overwrite.xml', new Style(), 'overwrite', 'Overwrite');
$screen->measureGrid($size);
// Hidden elements are found by id but are not shown, measured, or navigable.
expectOverwrite($screen->widget('overwrite-files') !== null, true, 'hidden widget is indexed');
expectOverwrite(shownLeaf($screen, 'overwrite-files'), null, 'hidden widget is not shown');
expectOverwrite(shownLeaf($screen, 'overwrite-title'), null, 'hidden layout child is not shown');
$text = shownLeaf($screen, 'overwrite-text');
expectOverwrite($text->grid()->height, 18, 'separator before a hidden layout is left out');
// A button with opens shows a widget in place of its parent and activates it.
$form = $screen->layout->findNode('overwrite-form');
$formGrid = $form->grid();
$browse = buttonLeaf($screen, 'Browse');
$screen->selectLeaf($browse);
$screen->handleEvent(overwriteKey(SDL::KEY_RETURN));
expectOverwrite($screen->takeLayoutChange(), true, 'show reports a layout change');
$screen->measureGrid($size);
$files = shownLeaf($screen, 'overwrite-files');
expectOverwrite($files !== null, true, 'widget is shown');
expectOverwrite($screen->activeLeaf(), $files, 'shown widget is active');
expectOverwrite($files->grid() == $formGrid, true, 'widget takes the target rectangle');
expectOverwrite(shownLeaf($screen, 'overwrite-file'), null, 'target content is hidden');
// Leaving the widget restores the target, then its deactivate runs, and focus returns.
$restoredFirst = null;
$screen->widget('overwrite-files')->on('deactivate', function() use (&$restoredFirst, $screen): void {
  $restoredFirst = shownLeaf($screen, 'overwrite-files') === null;
});
$screen->handleEvent(overwriteKey(SDL::KEY_ESCAPE));
expectOverwrite($restoredFirst, true, 'restore happens before deactivate');
expectOverwrite(Controller::$chosen, ['overwrite-files'], 'XML deactivate still runs');
expectOverwrite($screen->selectedLeaf(), $browse, 'focus returns to the opening button');
expectOverwrite(shownLeaf($screen, 'overwrite-file') !== null, true, 'target is shown again');
// A layout shown from code replaces a widget and focuses its first leaf.
$screen->measureGrid($size);
$textGrid = $text->grid();
expectOverwrite($screen->show('overwrite-details'), true, 'show layout from code');
expectOverwrite($screen->show('overwrite-details'), false, 'second show changes nothing');
$screen->measureGrid($size);
$title = shownLeaf($screen, 'overwrite-title');
expectOverwrite($screen->selectedLeaf(), $title, 'first layout leaf is selected');
expectOverwrite($screen->layout->findNode('overwrite-details')->grid() == $textGrid, true, 'layout takes the widget rectangle');
expectOverwrite(shownLeaf($screen, 'overwrite-text'), null, 'replaced widget is hidden');
// A layout stays while its widgets are left, and a restores button ends it.
$screen->handleEvent(overwriteKey(SDL::KEY_RETURN));
$screen->handleEvent(overwriteKey(SDL::KEY_ESCAPE));
expectOverwrite(shownLeaf($screen, 'overwrite-title') !== null, true, 'layout is not restored on deactivate');
$screen->selectLeaf(buttonLeaf($screen, 'Done'));
$screen->handleEvent(overwriteKey(SDL::KEY_RETURN));
expectOverwrite(shownLeaf($screen, 'overwrite-text') !== null, true, 'restores="parent" puts the widget back');
expectOverwrite($screen->selectedLeaf(), $browse, 'focus returns to the leaf selected before show');
expectOverwrite($screen->restore('overwrite-details'), false, 'restore of a hidden element');
expectOverwriteError(fn() => $screen->show('overwrite-text'), InvalidArgumentException::class, 'show needs an overwrite element');
// Layout over layout and leaf over leaf, built in code; the shown tile keeps the target size.
$root = new LayoutNode('vertical', '1*', '1*');
$first = new LayoutNode('vertical', '1*', '3', id: 'first');
$first->addLeaf(overwritePlaceholder('first-leaf', '1*'));
$root->addNode($first);
$root->addSeparator(new LayoutSeparator());
$replacement = new LayoutNode('vertical', '1*', '7', id: 'replacement');
$replacement->addLeaf(overwritePlaceholder('replacement-leaf', '1*'));
$replacement->setOverwrite('#first');
$root->addNode($replacement);
$root->addSeparator(new LayoutSeparator());
$root->addLeaf(overwritePlaceholder('small', '2'));
$wide = overwritePlaceholder('wide', '9');
$wide->setOverwrite('#small');
$root->addLeaf($wide);
$codeScreen = new Screen($root);
$smallLeaf = $root->leaves()[1];
$codeScreen->selectLeaf($smallLeaf);
$codeScreen->show('replacement', false);
expectOverwrite($codeScreen->selectedLeaf(), $smallLeaf, 'show without focus keeps the selection');
$codeScreen->show('wide');
$codeScreen->measureGrid($size);
expectOverwrite($replacement->grid()->height, 3, 'layout keeps the target height');
expectOverwrite($wide->grid()->height, 2, 'leaf keeps the target height');
expectOverwrite($codeScreen->restore('replacement'), true, 'restore layout from code');
expectOverwriteError(function() {
  $parentRoot = new LayoutNode('vertical', '1*', '1*');
  $leaf = overwritePlaceholder('top', '1*');
  $leaf->setOverwrite('parent');
  $parentRoot->addLeaf($leaf);
  (new Screen($parentRoot))->show('top');
}, LogicException::class, 'root layout cannot be replaced');
// A hidden element before a separator keeps that separator between the shown items.
$middleRoot = new LayoutNode('vertical', '1*', '1*');
$middleRoot->addLeaf(overwritePlaceholder('top-item', '1'));
$hiddenMiddle = overwritePlaceholder('hidden-middle', '1');
$hiddenMiddle->setOverwrite('#top-item');
$middleRoot->addLeaf($hiddenMiddle);
$middleRoot->addSeparator(new LayoutSeparator());
$middleRoot->addLeaf(overwritePlaceholder('bottom-item', '1*'));
$middleRoot->measureGrid(new Tile(0, 0, 10, 10));
expectOverwrite($middleRoot->leaves()[1]->grid()->y, 2, 'separator after a hidden element stays');
echo "Overwrite checks passed\n";
