<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Button\Button;
use SPTK\Widgets\Input\Input;
use SPTK\Widgets\List\ListView;

final class ListHotkeyAction {
  public static int $presses = 0;
  public static Screen $screen;
  public static LayoutLeaf $nameLeaf;

  public static function press(): void {
    self::$presses++;
    self::$screen->activateLeaf(self::$nameLeaf);
  }
}

function listHotkey(int $key, int $mod = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod, 'repeat' => false]];
}

function listHotkeyText(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

$list = new ListView(['First', 'Second', 'Third'], filterable: false, searchable: false, reorderable: true);
$reorders = 0;
$list->on('reorder', function() use (&$reorders): void { $reorders++; });
$layout = new LayoutNode('horizontal', '1*', '1*');
$layout->addLeaf(new LayoutLeaf('List', '1*', '1*', $list));
$name = new Input();
$nameLeaf = new LayoutLeaf('Input', '1*', '1*', $name);
$layout->addLeaf($nameLeaf);
$layout->addLeaf(new LayoutLeaf('Button', 'auto', '1*', new Button('New', 'n', ListHotkeyAction::class . '::press', new Style())));
$screen = new Screen($layout);
ListHotkeyAction::$screen = $screen;
ListHotkeyAction::$nameLeaf = $nameLeaf;
$screen->measureGrid(new Tile(0, 0, 40, 6));
$screen->handleEvent(listHotkey(SDL::KEY_RETURN));
$screen->handleEvent(listHotkey(SDL::KEY_DOWN, SDL::MOD_SHIFT));
if ($list->values() !== ['Second', 'First', 'Third'] || $list->getValue() !== 'First' || $reorders !== 1) {
  throw new RuntimeException('Active list reordering did not retain the selected item.');
}
$screen->handleEvent(listHotkey(ord('n')));
if (ListHotkeyAction::$presses !== 0) {
  throw new RuntimeException('Printable hotkey ran before its text input was consumed.');
}
$screen->handleEvent(listHotkeyText('n'));
if (ListHotkeyAction::$presses !== 1 || $list->filter() !== '' || $name->getValue() !== '' || $screen->activeLeaf() !== $nameLeaf) {
  throw new RuntimeException('Active list hotkey leaked text into the newly activated input.');
}
echo "List hotkeys OK\n";
