<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Core\{Color, Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\FileSelector\FileSelector;

/** Check one file selector result. */
function expectFile(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Build a key event using the current SDL input shape. */
function fileKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false]];
}

/** Build a text event with an FFI character buffer. */
function fileInput(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

/** Count file selector value changes. */
function fileChanged(): void {
  global $fileChanges;
  $fileChanges++;
}

/** Capture a file accepted through normal screen dispatch. */
function fileAcceptedTest(): void {
  global $acceptedFile, $screenSelector;
  $acceptedFile = $screenSelector->getValue();
}

/** Return one painted file selector row as text. */
function fileRow(Grid $grid, int $y): string {
  $row = '';
  for ($x = 0; $x < $grid->width(); $x++) {
    $row .= $grid->cell($x, $y)->glyph;
  }
  return $row;
}

$directory = sys_get_temp_dir() . '/sptk-files-' . bin2hex(random_bytes(5));
mkdir($directory);
mkdir($directory . '/Zebra');
mkdir($directory . '/alpha');
mkdir($directory . '/gone');
file_put_contents($directory . '/b.txt', 'b');
file_put_contents($directory . '/A.txt', 'a');
file_put_contents($directory . '/界.txt', 'wide');
file_put_contents($directory . '/.hidden', 'hidden');
try {
  $selector = new FileSelector($directory);
  $fileChanges = 0;
  $selector->on('change', fileChanged(...));
  expectFile($selector->path(), $directory, 'initial path resolves');
  expectFile(array_column($selector->items(), 'label'), ['..', '/alpha', '/gone', '/Zebra', ' .hidden', ' A.txt', ' b.txt', ' 界.txt'], 'parent, directories, then files');
  expectFile($selector->getValue(), dirname($directory), 'parent starts under cursor');
  $grid = new Grid(80, 4);
  $selector->paint(new GridWriter($grid, new Tile(0, 0, 80, 4)));
  expectFile(substr(fileRow($grid, 0), 0, strlen($directory)), $directory, 'fixed path header');
  expectFile($grid->cell(0, 0)->fg == (new Style())->highlight, true, 'path uses highlight color');
  $selector->emit('activate');
  $selector->handleInput(fileKey(SDL::KEY_PAGEDOWN));
  expectFile($selector->cursorPosition(), 2, 'paging excludes header row');
  $selector->handleInput(fileKey(SDL::KEY_END));
  $selector->paint(new GridWriter($grid, new Tile(0, 0, 80, 4)));
  expectFile($grid->cell(0, 0)->glyph, '/', 'header remains visible after scrolling');
  $selector->setFilter('al');
  expectFile($selector->activeValue(), $directory . '/alpha', 'directory filter ignores slash prefix');
  $selector->paint(new GridWriter($grid, new Tile(0, 0, 80, 4)));
  expectFile([$grid->cell(0, 1)->glyph, $grid->cell(1, 1)->fg == (new Style())->highlight], ['/', true], 'search highlights name after directory prefix');
  $before = $fileChanges;
  expectFile($selector->handleInput(fileKey(SDL::KEY_RETURN)), true, 'Return enters directory');
  expectFile([$selector->path(), $selector->active(), $selector->filter(), $fileChanges], [$directory . '/alpha', true, '', $before + 1], 'navigation keeps list active and emits change');
  expectFile(array_column($selector->items(), 'label'), ['..'], 'empty directory has parent row');
  $selector->handleInput(fileKey(SDL::KEY_RETURN));
  expectFile([$selector->path(), $selector->activeValue()], [$directory, $directory . '/alpha'], 'parent restores departed directory cursor');
  $selector->setFilter('b');
  expectFile($selector->activeValue(), $directory . '/b.txt', 'file filter ignores space prefix');
  expectFile($selector->handleInput(fileKey(SDL::KEY_RETURN)), false, 'Return on file falls through to screen acceptance');
  expectFile($selector->releaseNotification(SDL::KEY_RETURN, 0), 'accept', 'screen receives accept notification');
  $selector->emit('deactivate');
  expectFile([$selector->active(), $selector->filter()], [false, ''], 'deactivation clears query');
  $selector->setValue($directory . '/gone');
  rmdir($directory . '/gone');
  $selector->emit('activate');
  expectFile($selector->handleInput(fileKey(SDL::KEY_RETURN)), true, 'failed navigation is consumed');
  expectFile($selector->path(), $directory, 'failed navigation retains path');
  expectFile($selector->error() !== null, true, 'failed navigation reports error');
  $selector->paint(new GridWriter($grid, new Tile(0, 0, 80, 4)));
  expectFile(str_contains(fileRow($grid, 0), 'Cannot read directory'), true, 'error appears in header');
  $selector->reload();
  expectFile([in_array($directory . '/gone', $selector->values(), true), $selector->error()], [false, null], 'reload refreshes entries and clears error');
  file_put_contents($directory . "/\xff.bin", 'raw');
  $selector->reload();
  expectFile(in_array($directory . "/\xff.bin", $selector->values(), true), true, 'non-UTF-8 filename keeps its raw full path');
  try {
    $selector->setPath($directory . '/b.txt');
    throw new RuntimeException('file accepted as directory');
  } catch (RuntimeException $error) {
    expectFile($error->getMessage() !== 'file accepted as directory', true, 'file path rejected');
  }
  expectFile($selector->path(), $directory, 'programmatic failure preserves listing');
  $selector->setPath('alpha');
  expectFile($selector->path(), $directory . '/alpha', 'relative path follows current directory');
  $selector->setPath('..');
  $root = new FileSelector('/');
  expectFile([$root->items()[0]['label'], $root->activeValue()], ['/', '/'], 'root parent row selects root');
  $multiple = new FileSelector($directory, multiple: true);
  $multiple->emit('activate');
  $multiple->setFilter('b');
  $multiple->handleInput(fileKey(SDL::KEY_SPACE));
  $multiple->setFilter('界');
  $multiple->handleInput(fileKey(SDL::KEY_SPACE));
  expectFile($multiple->getValue(), [$directory . '/b.txt', $directory . '/界.txt'], 'multiple selection preserves item order');
  try {
    $multiple->setValue([$directory . '/b.txt', 4]);
    throw new RuntimeException('non-string path accepted');
  } catch (InvalidArgumentException $error) {
    expectFile($multiple->getValue(), [$directory . '/b.txt', $directory . '/界.txt'], 'invalid path array preserves selection');
  }
  $multiple->emit('deactivate');
  expectFile($multiple->filter(), '', 'multiple selection clears query on release');
  $screenSelector = new FileSelector($directory);
  $screenSelector->on('accept', fileAcceptedTest(...));
  $layout = new LayoutNode('vertical', '1*', '1*');
  $layout->addLeaf(new LayoutLeaf('FileSelector', '', '1*', $screenSelector));
  $screen = new Screen($layout);
  $screen->measureGrid(new Tile(0, 0, 40, 6));
  $acceptedFile = null;
  $screen->handleEvent(fileKey(SDL::KEY_RETURN));
  $screenSelector->setValue($directory . '/b.txt');
  $screen->handleEvent(fileKey(SDL::KEY_RETURN));
  expectFile([$acceptedFile, $screenSelector->active(), $screen->activeLeaf()], [$directory . '/b.txt', false, null], 'screen accepts file and releases selector');
  $reader = new XMLReader();
  $reader->XML('<FileSelector path="' . htmlspecialchars($directory, ENT_QUOTES | ENT_XML1) . '" multiple="true"><Style><Background>#202020</Background></Style><Event type="accept" action="Controller::fileAccepted" /></FileSelector>');
  $reader->read();
  $parser = new SPTK\Widgets\FileSelector\Parser();
  $parser->validateAttributes($reader, []);
  $definition = $parser->parse($reader, new Style());
  expectFile([$definition->widget->path(), $definition->widget->background() == Color::from('#202020'), count($definition->events)], [$directory, true, 1], 'XML path style and event');
  foreach (['<FileSelector path="/missing/sptk-file-selector" />', '<FileSelector multiple="maybe" />', '<FileSelector>body</FileSelector>', '<FileSelector><Text /></FileSelector>'] as $invalid) {
    $reader = new XMLReader();
    $reader->XML($invalid);
    $reader->read();
    try {
      $parser->validateAttributes($reader, []);
      $parser->parse($reader, new Style());
      throw new RuntimeException('invalid FileSelector XML accepted');
    } catch (InvalidArgumentException|RuntimeException $error) {
      expectFile($error->getMessage() !== 'invalid FileSelector XML accepted', true, 'invalid XML rejected');
    }
  }
  $screens = (new SPTK\XmlParser\XmlParser())->windows[0]['screens'];
  $demo = $screens[count($screens) - 1];
  expectFile($demo->widget('demoFiles') instanceof FileSelector, true, 'demo screen contains file selector');
  expectFile($demo->widget('demoFiles')->path(), realpath(APP_DIR . '/../Demo'), 'demo path is relative to screen XML');
  $demo->measureGrid(new Tile(0, 0, 98, 29));
  $demo->paint(new Grid(98, 29));
  $colorTile = null;
  $dateTile = null;
  $fileTile = null;
  foreach ($demo->layout->leaves() as $leaf) {
    if ($leaf->instance()->id() === 'demoColor') {
      $colorTile = $leaf->grid();
    } else if ($leaf->instance()->id() === 'demoDate') {
      $dateTile = $leaf->grid();
    } else if ($leaf->instance()->id() === 'demoFiles') {
      $fileTile = $leaf->grid();
    }
  }
  expectFile($colorTile !== null && $dateTile !== null && $fileTile !== null && $colorTile->y === $dateTile->y && $fileTile->y > $dateTile->y + $dateTile->height, true, 'file selector sits below color and date selectors');
} finally {
  foreach (scandir($directory) as $name) {
    if ($name === '.' || $name === '..') {
      continue;
    }
    $entry = $directory . '/' . $name;
    if (is_dir($entry)) {
      rmdir($entry);
    } else {
      unlink($entry);
    }
  }
  rmdir($directory);
}
echo "File selector checks passed\n";
