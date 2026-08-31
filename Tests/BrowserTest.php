<?php

namespace SPTK\Tests;

use SPTK\Core\GridBuffer;
use SPTK\Core\ElementContext;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\DirectoryBrowser;
use SPTK\Widgets\FileBrowser;

return [
  'directory browser lists and enters directories' => function(): void {
    $root = browserFixture();
    $browser = new DirectoryBrowser('dirs', $root);
    $browser->setFrame(new Rect(0, 0, 12, 4));
    $buffer = new GridBuffer(12, 4);
    $browser->render($buffer);

    assertSame($root, $browser->getValue(), 'DirectoryBrowser value should be the current path.');
    assertSame(' ..         ', $buffer->line(0), 'DirectoryBrowser should include parent navigation.');
    $browser->handle(InputEvent::key('Down'));
    $browser->handle(InputEvent::key('Enter'));
    assertSame($root . DIRECTORY_SEPARATOR . 'alpha', $browser->getValue(), 'Enter should change to the active directory.');
    cleanupBrowserFixture($root);
  },

  'directory browser supports search' => function(): void {
    $root = browserFixture();
    $browser = new DirectoryBrowser('dirs', $root);
    $browser->setFrame(new Rect(0, 0, 12, 4));
    $browser->handle(InputEvent::text('b'));

    assertSame('beta', $browser->activeItem()?->text(), 'DirectoryBrowser should inherit ListView search.');
    cleanupBrowserFixture($root);
  },

  'directory browser path changes invalidate render' => function(): void {
    $root = browserFixture();
    $context = new ElementContext();
    $context->clearRender();
    $browser = new DirectoryBrowser('dirs', $root);
    $browser->setContext($context);
    assertSame(3, $browser->preferredRows(), 'DirectoryBrowser preferred height should match its current item count.');
    $browser->setPath($root . DIRECTORY_SEPARATOR . 'alpha');

    assertTrue($context->renderDirty(), 'DirectoryBrowser should invalidate render after changing directory.');
    assertTrue($context->layoutDirty(), 'DirectoryBrowser should invalidate layout after changing item count.');
    assertSame(1, $browser->preferredRows(), 'DirectoryBrowser preferred height should update after changing directory.');
    cleanupBrowserFixture($root);
  },

  'file browser filters and selects files' => function(): void {
    $root = browserFixture();
    $browser = new FileBrowser('files', $root, ['extensions' => ['txt'], 'multiple' => true]);
    $browser->setFrame(new Rect(0, 0, 16, 4));
    $buffer = new GridBuffer(16, 4);
    $browser->render($buffer);

    assertSame('   note.txt     ', $buffer->line(0), 'FileBrowser should list files matching the extension filter with selection marker space.');
    assertSame(null, $browser->item(1), 'FileBrowser should omit files outside the extension filter.');
    $browser->handle(InputEvent::key('Space'));
    assertSame([$root . DIRECTORY_SEPARATOR . 'note.txt'], $browser->getValue(), 'FileBrowser value should be selected file paths.');
    cleanupBrowserFixture($root);
  },

  'file browser single selection returns selected file array' => function(): void {
    $root = browserFixture();
    $browser = new FileBrowser('files', $root);
    $browser->handle(InputEvent::key('Space'));
    $browser->handle(InputEvent::key('Down'));
    $browser->handle(InputEvent::key('Space'));

    assertSame([$root . DIRECTORY_SEPARATOR . 'script.php'], $browser->getValue(), 'Single FileBrowser selection should keep only one selected file.');
    cleanupBrowserFixture($root);
  },

  'file browser single selected file can be deselected' => function(): void {
    $root = browserFixture();
    $browser = new FileBrowser('files', $root);
    $browser->handle(InputEvent::key('Space'));
    assertSame([$root . DIRECTORY_SEPARATOR . 'note.txt'], $browser->getValue(), 'Space should select the active file.');
    $browser->handle(InputEvent::key('Space'));

    assertSame([], $browser->getValue(), 'Space on the selected file should deselect it.');
    cleanupBrowserFixture($root);
  },

  'file browser can list directories only without parent entry' => function(): void {
    $root = browserFixture();
    $browser = new FileBrowser('files', $root, ['mode' => 'directories']);

    assertSame(2, $browser->preferredRows(), 'Directory mode preferred height should match the directory count.');
    assertSame('alpha', $browser->activeItem()?->text(), 'Directory mode should list child directories without a trailing parent entry.');
    assertSame('beta', $browser->item(1)?->text(), 'Directory mode should include sibling directories.');
    assertSame(null, $browser->item(2), 'Directory mode should omit files.');
    $browser->handle(InputEvent::key('Space'));
    assertSame([$root . DIRECTORY_SEPARATOR . 'alpha'], $browser->getValue(), 'Directory mode should return selected directory paths.');
    cleanupBrowserFixture($root);
  },
];

function browserFixture(): string {
  $root = sys_get_temp_dir() . '/sptk-browser-' . bin2hex(random_bytes(4));
  mkdir($root);
  mkdir($root . '/alpha');
  mkdir($root . '/beta');
  file_put_contents($root . '/note.txt', 'note');
  file_put_contents($root . '/script.php', '<?php');
  return realpath($root) ?: $root;
}

function cleanupBrowserFixture(string $root): void {
  foreach (['note.txt', 'script.php'] as $file) {
    if (is_file($root . DIRECTORY_SEPARATOR . $file)) {
      unlink($root . DIRECTORY_SEPARATOR . $file);
    }
  }
  foreach (['alpha', 'beta'] as $dir) {
    if (is_dir($root . DIRECTORY_SEPARATOR . $dir)) {
      rmdir($root . DIRECTORY_SEPARATOR . $dir);
    }
  }
  if (is_dir($root)) {
    rmdir($root);
  }
}
