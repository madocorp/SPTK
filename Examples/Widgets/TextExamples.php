<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\Label;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\TextBlock;

class TextExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Text');
    $menu->addItem([
      'label' => 'Label',
      'action' => function() use ($host): void {
        $panel = $host->panel('label-example', 'Label', 'small');
        $panel->addContent(new Label('label-demo', 'Static one-line text'));
        $host->showPanel('Text / Label', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'TextBlock',
      'action' => function() use ($host): void {
        $panel = $host->panel('textblock-example', 'TextBlock', 'normal');
        $panel->addContent(new TextBlock(
          'textblock-demo',
          'TextBlock wraps longer prose to the available panel width. Resize the window to see layout recompute.'
        ));
        $host->showPanel('Text / TextBlock', $panel);
      },
    ]);
    return $menu;
  }

}
