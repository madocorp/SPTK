<?php

namespace SPTK\Tests;

use SPTK\Core\ElementContext;
use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\DialogLayer;
use SPTK\Widgets\DirectoryBrowser;
use SPTK\Widgets\DirectorySelector;
use SPTK\Widgets\Dock;
use SPTK\Widgets\ColorPicker;
use SPTK\Widgets\ColorSelector;
use SPTK\Widgets\ColorSelectorPickerPanel;
use SPTK\Widgets\FileBrowser;
use SPTK\Widgets\FileSelector;
use SPTK\Widgets\FileSelectorBrowserPanel;
use SPTK\Widgets\Input;
use SPTK\Widgets\ListView;
use SPTK\Widgets\Selector;

return [
  'selector renders input-like field' => function(): void {
    $selector = new Selector('size', ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'], 'm');
    $selector->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $selector->render($buffer);

    assertSame('Medium ...', $buffer->line(0), 'Selector should render the selected label and action marker.');
    assertSame('#00aaaa', $buffer->cell(1, 0)?->bg->hex(), 'Selector should use the input field background.');
    assertSame('#00ffff', $buffer->cell(7, 0)?->fg->hex(), 'Selector marker should use the marker color.');

    $selector->setFocused(true);
    $buffer = new GridBuffer(10, 1);
    $selector->render($buffer);
    assertSame('#000000', $buffer->cell(7, 0)?->bg->hex(), 'Focused selector should highlight the action marker.');
    assertSame('#00aaaa', $buffer->cell(0, 0)?->bg->hex(), 'Focused selector should not draw a cursor over the text.');
  },

  'selector does not open without a dialog layer' => function(): void {
    $selector = new Selector('size', ['Small', 'Medium']);
    assertSame(false, $selector->handle(InputEvent::key('Enter')), 'Selector should require a dialog layer to open.');
  },

  'selector opens panel and updates value from list' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new Selector('size', ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'], 's');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 12));
    $focus = new FocusManager($root);

    assertSame($selector, $focus->current(), 'Selector should start focused.');
    assertSame(true, $focus->dispatch(InputEvent::key('Enter')), 'Enter should open the selector panel.');
    assertInstanceOf(ListView::class, $focus->current(), 'Selector panel should focus the list.');

    $focus->dispatch(InputEvent::key('Down'));
    $focus->dispatch(InputEvent::key('Enter'));
    assertSame('m', $selector->getValue(), 'Selecting a list row should update the selector value.');
    assertSame(null, $dialogs->top(), 'Selector panel should close after selection.');
    assertSame($selector, $focus->current(), 'Focus should return to the selector after selection.');
  },

  'directory selector commits browser path with ok' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new DirectorySelector('directory', $fixture);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 60, 14));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(DirectoryBrowser::class, $focus->current(), 'DirectorySelector should open a DirectoryBrowser.');
    $focus->dispatch(InputEvent::key('Down'));
    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame($fixture . DIRECTORY_SEPARATOR . 'alpha', $selector->getValue(), 'OK should commit the browser directory path.');
    assertSame(null, $dialogs->top(), 'Directory selector panel should close on OK.');
    assertSame($selector, $focus->current(), 'Focus should return to the directory selector.');
    cleanupSelectorFixture($fixture);
  },

  'directory selector shows current path in its panel' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new DirectorySelector('directory', $fixture);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $dialogs->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $dialogs->layout();
    $buffer = new GridBuffer(70, 16);
    $root->render($buffer);
    assertTrue(bufferContains($buffer, 'Path:'), 'DirectorySelector panel should show a path label.');
    assertTrue(bufferContains($buffer, $fixture), 'DirectorySelector panel should show the current path.');
    $focus->dispatch(InputEvent::key('Down'));
    $focus->dispatch(InputEvent::key('Enter'));
    $dialogs->layout();
    $buffer = new GridBuffer(70, 16);
    $root->render($buffer);
    assertTrue(bufferContains($buffer, $fixture . DIRECTORY_SEPARATOR . 'alpha'), 'DirectorySelector panel path should update after navigation.');
    cleanupSelectorFixture($fixture);
  },

  'color selector commits picker value with ok' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new ColorSelector('color', '#000000');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(ColorPicker::class, $focus->current()?->parent(), 'ColorSelector should open with the ColorPicker focused.');
    $dialogs->setFrame(new Rect(0, 0, 70, 16));
    $dialogs->layout();
    $focus->dispatch(InputEvent::key('Right'));
    $body = $dialogs->top()?->content()->children()[0] ?? null;
    assertInstanceOf(ColorSelectorPickerPanel::class, $body, 'ColorSelector panel should contain the selector picker body.');
    assertInstanceOf(ColorPicker::class, $body->picker(), 'ColorSelector picker body should contain the swatch picker.');
    assertSame(64, $body->frame()->width, 'ColorSelector dialog should reserve exactly the picker content width.');
    assertSame(7, $body->frame()->height, 'ColorSelector dialog should allocate room for the picker and input row.');
    assertSame(1, $body->input()->frame()->height, 'ColorSelector dialog should keep the input row visible.');
    assertSame(1, $body->preview()->frame()->height, 'ColorSelector dialog should keep the preview row visible.');
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame('#111111', $selector->getValue(), 'OK should commit the picker color value.');
    assertSame(null, $dialogs->top(), 'Color selector panel should close on OK.');
    assertSame($selector, $focus->current(), 'Focus should return to the color selector.');
  },

  'color selector cancel keeps current value' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new ColorSelector('color', '#000000');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Right'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame('#000000', $selector->getValue(), 'Cancel should leave the color selector value unchanged.');
    assertSame(null, $dialogs->top(), 'Color selector panel should close on Cancel.');
  },

  'color selector input updates preview and picker' => function(): void {
    $body = new ColorSelectorPickerPanel('color-body', '#000000');
    $body->setFrame(new Rect(0, 0, 64, 8));
    $input = $body->input();
    $input->setCursorPosition(7);
    foreach (array_fill(0, 7, InputEvent::key('Backspace')) as $event) {
      $input->handle($event);
    }
    foreach (str_split('#123456') as $char) {
      $input->handle(InputEvent::text($char));
    }
    $buffer = new GridBuffer(64, 8);
    $body->render($buffer);

    assertSame('#123456', $body->getValue(), 'Typing a valid hex color should update the selector body value.');
    assertSame('#123456', $body->preview()->color(), 'Typing a valid hex color should update the preview strip value.');
    assertSame('#123456', $body->picker()->getValue(), 'Typing a valid hex color should update the swatch picker selection.');
    assertSame('#123456', $buffer->cell(11, 6)?->bg->hex(), 'Preview strip should render the freely typed color after the input gap.');
    assertSame('#123456', $buffer->cell(61, 4)?->bg->hex(), 'The picker exact row should render the freely typed color in the last slot.');
    assertSame('#123456', $buffer->cell(62, 4)?->bg->hex(), 'The picker exact row should render a two-cell freely typed color swatch.');
  },

  'file selector commits single file path with ok' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, null, ['extensions' => ['txt']]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 60, 14));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(DirectoryBrowser::class, $focus->current(), 'FileSelector should open with the DirectoryBrowser focused.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertInstanceOf(FileBrowser::class, $focus->current(), 'Tab should move to the FileBrowser.');
    $focus->dispatch(InputEvent::key('Space'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame($fixture . DIRECTORY_SEPARATOR . 'note.txt', $selector->getValue(), 'Single FileSelector should commit the selected file path string.');
    assertSame(null, $dialogs->top(), 'File selector panel should close on OK.');
    assertSame($selector, $focus->current(), 'Focus should return to the file selector.');
    cleanupSelectorFixture($fixture);
  },

  'file selector uses browser panel default dimensions' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, null, ['extensions' => ['txt']]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 100, 40));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $root->setFrame(new Rect(0, 0, 100, 40));
    $panel = $dialogs->top();

    assertSame(FileSelectorBrowserPanel::DEFAULT_PANEL_COLUMNS, $panel?->contentColumns(), 'File selector should use the default browser panel width.');
    assertTrue(($panel?->frame()->height ?? 0) > FileSelectorBrowserPanel::DEFAULT_PANEL_ROWS, 'File selector panel should be tall enough for the default browser body.');
    cleanupSelectorFixture($fixture);
  },

  'file selector commits multiple file paths with ok' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('files', $fixture, null, ['multiple' => true]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 60, 14));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Space'));
    $focus->dispatch(InputEvent::key('Down'));
    $focus->dispatch(InputEvent::key('Space'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame([
      $fixture . DIRECTORY_SEPARATOR . 'note.txt',
      $fixture . DIRECTORY_SEPARATOR . 'script.php',
    ], $selector->getValue(), 'Multiple FileSelector should commit selected file path array.');
    assertSame($fixture . DIRECTORY_SEPARATOR . '[2 files]', $selector->text(), 'Multiple FileSelector should display a compact selected-file count.');
    cleanupSelectorFixture($fixture);
  },

  'file selector summarizes multiple files from different directories' => function(): void {
    $fixture = selectorFixture();
    $selector = new FileSelector('files', $fixture, [
      $fixture . DIRECTORY_SEPARATOR . 'note.txt',
      $fixture . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'inside.txt',
    ], ['multiple' => true]);

    assertSame('...' . DIRECTORY_SEPARATOR . '[2 files]', $selector->text(), 'Multiple FileSelector should use an abbreviated path for files from different directories.');
    cleanupSelectorFixture($fixture);
  },

  'file selector synchronizes directory and file panes' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, null, ['extensions' => ['txt']]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(DirectoryBrowser::class, $focus->current(), 'FileSelector should start in the directory pane.');
    $focus->dispatch(InputEvent::key('Down'));
    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    assertInstanceOf(FileBrowser::class, $focus->current(), 'Tab should move to the synchronized file pane.');
    assertSame('inside.txt', $focus->current()->activeItem()?->text(), 'File pane should rerender for the selected directory.');
    $focus->dispatch(InputEvent::key('Space'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame($fixture . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'inside.txt', $selector->getValue(), 'OK should commit a file from the synchronized file pane.');
    cleanupSelectorFixture($fixture);
  },

  'file selector opens in the directory of the current value' => function(): void {
    $fixture = selectorFixture();
    $value = $fixture . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'inside.txt';
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, $value, ['extensions' => ['txt']]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    assertInstanceOf(FileBrowser::class, $focus->current(), 'FileSelector should focus the file pane after tab.');
    assertSame('inside.txt', $focus->current()->activeItem()?->text(), 'FileSelector should open the file pane in the selected value directory.');
    assertSame([$value], $focus->current()->getValue(), 'FileSelector should mark the current value selected when it is visible.');
    cleanupSelectorFixture($fixture);
  },

  'file selector can create and select a file' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, null, ['createFile' => true]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::text('created.txt'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(FileBrowser::class, $focus->current(), 'FileSelector should return focus to the file browser after creating a file.');
    assertSame([$fixture . DIRECTORY_SEPARATOR . 'created.txt'], $focus->current()->getValue(), 'Created file should be selected in the file browser.');
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame($fixture . DIRECTORY_SEPARATOR . 'created.txt', $selector->getValue(), 'OK should commit the created file.');
    cleanupSelectorFixture($fixture);
  },

  'file selector can create and enter a directory' => function(): void {
    $fixture = selectorFixture();
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new FileSelector('file', $fixture, null, ['createDirectory' => true]);
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 70, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::text('created-dir'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertInstanceOf(FileBrowser::class, $focus->current(), 'FileSelector should return focus to the file browser after creating a directory.');
    assertSame($fixture . DIRECTORY_SEPARATOR . 'created-dir', $focus->current()->path(), 'Created directory should become the active browser path.');
    cleanupSelectorFixture($fixture);
  },
];

function selectorFixture(): string {
  $root = sys_get_temp_dir() . '/sptk-selector-' . bin2hex(random_bytes(4));
  mkdir($root);
  mkdir($root . '/alpha');
  file_put_contents($root . '/alpha/inside.txt', 'inside');
  file_put_contents($root . '/note.txt', 'note');
  file_put_contents($root . '/script.php', '<?php');
  return realpath($root) ?: $root;
}

function cleanupSelectorFixture(string $root): void {
  foreach (['note.txt', 'script.php', 'created.txt'] as $file) {
    if (is_file($root . DIRECTORY_SEPARATOR . $file)) {
      unlink($root . DIRECTORY_SEPARATOR . $file);
    }
  }
  if (is_file($root . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'inside.txt')) {
    unlink($root . DIRECTORY_SEPARATOR . 'alpha' . DIRECTORY_SEPARATOR . 'inside.txt');
  }
  if (is_dir($root . DIRECTORY_SEPARATOR . 'alpha')) {
    rmdir($root . DIRECTORY_SEPARATOR . 'alpha');
  }
  if (is_dir($root . DIRECTORY_SEPARATOR . 'created-dir')) {
    rmdir($root . DIRECTORY_SEPARATOR . 'created-dir');
  }
  if (is_dir($root)) {
    rmdir($root);
  }
}

function bufferContains(GridBuffer $buffer, string $needle): bool {
  for ($row = 0; $row < $buffer->height(); $row++) {
    if (str_contains($buffer->line($row), $needle)) {
      return true;
    }
  }
  return false;
}
