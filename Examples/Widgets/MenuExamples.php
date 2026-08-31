<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\Label;
use SPTK\Widgets\MenuItem;

class MenuExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem([
      'label' => 'Menu',
      'layout' => ['rightValues' => ['>', 'on', 'off']],
    ]);
    $menu->addItem(self::nested($host));
    $menu->addItem(self::selectable($host));
    $menu->addItem(self::searchable($host));
    $menu->addItem(self::longList($host));
    return $menu;
  }

  protected static function nested(ExampleHost $host): MenuItem {
    $item = new MenuItem('Nested Submenus');
    $level = new MenuItem('Level 2');
    $level->addItem([
      'label' => 'Leaf Action',
      'action' => fn() => $host->showMain('Menu / Nested Submenus', new Label('menu-nested-label', 'Nested submenu action selected.')),
    ]);
    $item->addItem($level);
    return $item;
  }

  protected static function selectable(ExampleHost $host): MenuItem {
    $item = new MenuItem(['label' => 'Selectable Items', 'layout' => ['leftValues' => ['', '*']]]);
    foreach (['Grid', 'Guides', 'Rulers'] as $label) {
      $item->addItem(new MenuItem([
        'label' => $label,
        'selectable' => true,
        'action' => function($popup, int $index, MenuItem $menuItem) use ($host, $label): void {
          $menuItem->update(['checked' => !$menuItem->checked()]);
          $popup->updateItem($index, ['checked' => $menuItem->checked()]);
          $host->showMain('Menu / Selectable Items', new Label('menu-selectable-label', "{$label} toggled"));
        },
      ]));
    }
    return $item;
  }

  protected static function searchable(ExampleHost $host): MenuItem {
    $item = new MenuItem([
      'label' => 'Searchable And Filterable',
      'layout' => [
        'filterable' => true,
        'rightValues' => ['table', 'view', 'system'],
      ],
    ]);
    foreach ([
      ['users', 'table'],
      ['active_orders', 'view'],
      ['information_schema', 'system'],
      ['events', 'table'],
      ['reports', 'view'],
    ] as [$label, $type]) {
      $item->addItem([
        'label' => $label,
        'right' => $type,
        'action' => fn() => $host->showMain('Menu / Searchable And Filterable', new Label('menu-search-label', "{$label} selected")),
      ]);
    }
    return $item;
  }

  protected static function longList(ExampleHost $host): MenuItem {
    $item = new MenuItem([
      'label' => 'Long Popup List',
      'layout' => [
        'filterable' => true,
        'maxHeightRows' => 12,
        'rightValues' => ['demo'],
      ],
    ]);
    for ($i = 1; $i <= 40; $i++) {
      $label = 'Item ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
      $item->addItem([
        'label' => $label,
        'right' => 'demo',
        'separatorAfter' => $i % 10 === 0,
        'action' => fn() => $host->showMain('Menu / Long Popup List', new Label('menu-long-label', "{$label} selected")),
      ]);
    }
    return $item;
  }

}
