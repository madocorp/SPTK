<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\DirectoryBrowser;
use SPTK\Widgets\FileBrowser;
use SPTK\Widgets\MenuItem;

class BrowserExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Browsers');
    $menu->addItem([
      'label' => 'DirectoryBrowser',
      'action' => function() use ($host): void {
        $panel = $host->panel('directory-browser-example-panel', 'DirectoryBrowser', 'big');
        $panel->addContent(new DirectoryBrowser('directory-browser-example', getcwd()), 14);
        $host->showPanel('Browsers / DirectoryBrowser', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'FileBrowser',
      'action' => function() use ($host): void {
        $panel = $host->panel('file-browser-example-panel', 'FileBrowser', 'big');
        $panel->addContent(new FileBrowser('file-browser-example', getcwd(), [
          'multiple' => true,
        ]), 14);
        $host->showPanel('Browsers / FileBrowser', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'PHP Files',
      'action' => function() use ($host): void {
        $panel = $host->panel('file-browser-php-example-panel', 'PHP Files', 'big');
        $panel->addContent(new FileBrowser('file-browser-php-example', getcwd(), [
          'extensions' => ['php'],
          'multiple' => true,
        ]), 14);
        $host->showPanel('Browsers / FileBrowser / PHP Files', $panel);
      },
    ]);
    return $menu;
  }

}
