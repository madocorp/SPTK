<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\ListView;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\TableView;

class CollectionExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Collections');
    $list = new MenuItem('ListView');
    $listDefault = new MenuItem('Default');
    $listDefault->addItem([
      'label' => 'Main',
      'action' => fn() => $host->showMain('Collections / ListView / Default / Main', self::defaultList('list-default-main')),
    ]);
    $listDefault->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'ListView Default', self::defaultList('list-default-panel'), 6),
    ]);
    $list->addItem($listDefault);
    $listSelectable = new MenuItem('Selectable');
    $listSelectable->addItem([
      'label' => 'Main',
      'action' => fn() => $host->showMain('Collections / ListView / Selectable / Main', self::selectableList('list-selectable-main')),
    ]);
    $listSelectable->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'ListView Selectable', self::selectableList('list-selectable-panel'), 6),
    ]);
    $list->addItem($listSelectable);
    $list->addItem([
      'label' => 'Searchable',
      'action' => fn() => $host->showMain('Collections / ListView / Searchable', new ListView('list-searchable', [
        ['text' => 'Alpha', 'filterable' => true],
        ['text' => 'Beta', 'filterable' => true],
        ['text' => 'Gamma', 'filterable' => true],
        ['text' => 'Delta', 'filterable' => true],
        ['text' => 'Alpine', 'filterable' => true],
      ], ['filterable' => true])),
    ]);
    $menu->addItem($list);

    $table = new MenuItem('TableView');
    $tableDefault = new MenuItem('Default');
    $tableDefault->addItem([
      'label' => 'Main',
      'action' => fn() => $host->showMain('Collections / TableView / Default / Main', self::defaultTable('table-default-main')),
    ]);
    $tableDefault->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'TableView Default', self::defaultTable('table-default-panel'), 8, 'big'),
    ]);
    $table->addItem($tableDefault);
    $table->addItem([
      'label' => 'Row Numbers',
      'action' => function() use ($host): void {
        $view = self::defaultTable();
        $view->setRowNumbers(true);
        $host->showMain('Collections / TableView / Row Numbers', $view);
      },
    ]);
    $tableRowCursor = new MenuItem('Row Cursor');
    $tableRowCursor->addItem([
      'label' => 'Main',
      'action' => fn() => $host->showMain('Collections / TableView / Row Cursor / Main', self::rowCursorTable('table-row-cursor-main')),
    ]);
    $tableRowCursor->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'TableView Row Cursor', self::rowCursorTable('table-row-cursor-panel'), 7, 'big'),
    ]);
    $table->addItem($tableRowCursor);
    $table->addItem([
      'label' => 'Wide Scroll',
      'action' => fn() => $host->showMain('Collections / TableView / Wide Scroll', new TableView('table-wide', ['id', 'description', 'status'], [
        [1, str_repeat('wide cell ', 8), 'ready'],
        [2, str_repeat('scroll me ', 9), 'running'],
        [3, str_repeat('another long value ', 5), 'done'],
      ])),
    ]);
    $table->addItem([
      'label' => 'Search Highlight',
      'action' => fn() => $host->showMain('Collections / TableView / Search Highlight', self::highlightedTable()),
    ]);
    $table->addItem([
      'label' => 'Large Mixed',
      'action' => fn() => $host->showMain('Collections / TableView / Large Mixed', self::largeMixedTable()),
    ]);
    $menu->addItem($table);
    return $menu;
  }

  protected static function defaultList(string $name): ListView {
    return new ListView($name, [
      ['text' => 'main', 'right' => 'active'],
      ['text' => 'information_schema', 'right' => 'system'],
      ['text' => 'analytics', 'right' => 'warehouse'],
    ]);
  }

  protected static function selectableList(string $name): ListView {
    return new ListView($name, [
      ['text' => 'main', 'selectable' => 'database', 'selected' => true, 'right' => 'active'],
      ['text' => 'information_schema', 'selectable' => 'database', 'right' => 'system'],
      ['text' => 'analytics', 'selectable' => 'database', 'right' => 'warehouse'],
    ]);
  }

  protected static function defaultTable(string $name = 'table-default'): TableView {
    return new TableView($name, ['id', 'name', 'email'], [
      [1, 'Ada', 'ada@example.test'],
      [2, 'Grace', 'grace@example.test'],
      [3, 'Linus', 'linus@example.test'],
      [4, 'Margaret', 'margaret@example.test'],
    ], [5, 14, 26]);
  }

  protected static function rowCursorTable(string $name): TableView {
    $table = new TableView($name, ['database', 'owner', 'status'], [
      ['main', 'Ada', 'active'],
      ['information_schema', 'system', 'readonly'],
      ['analytics', 'Grace', 'warehouse'],
      ['archive', 'Linus', 'cold'],
      ['staging', 'Margaret', 'syncing'],
    ], ['45%', '25%', '30%']);
    $table->setRowCursor();
    $table->setColumnAlignments([
      'owner' => 'center',
      'status' => 'right',
    ]);
    return $table;
  }

  protected static function highlightedTable(): TableView {
    $table = new TableView('table-highlighted', ['id', 'service', 'status', 'owner', 'note'], [
      [101, 'api-gateway', 'healthy', 'Ada', 'latency normal'],
      [102, 'worker-a', 'warning', 'Grace', 'retry queue growing'],
      [103, 'billing-sync', 'critical', 'Linus', 'critical timeout on batch import'],
      [104, 'reporting', 'healthy', 'Margaret', 'nightly export complete'],
      [105, 'notifications', 'critical', 'Barbara', "critical webhook backlog\nretrying providers"],
      [106, 'search-index', 'warning', 'Alan', 'replica lag above target'],
      [107, 'audit-log', 'critical', 'Katherine', 'critical disk watermark'],
    ], [7, 18, 12, 14, 34]);
    $table->setRowNumbers(true);
    $table->search('critical', ['columns' => ['status', 'note']]);
    return $table;
  }

  protected static function largeMixedTable(): TableView {
    $header = [
      'id',
      'name',
      'team',
      'status',
      'priority',
      'owner',
      'region',
      'started',
      'updated',
      'score',
      'notes',
      'details',
    ];
    $names = ['Ada', 'Grace', 'Linus', 'Margaret', 'Barbara', 'Alan', 'Katherine', 'Donald'];
    $teams = ['Core', 'Renderer', 'Runtime', 'Widgets', 'Docs'];
    $statuses = ['ready', 'running', 'blocked', 'queued', 'done'];
    $regions = ['EU', 'US', 'APAC', 'LATAM'];
    $rows = [];
    for ($i = 1; $i <= 1200; $i++) {
      $rows[] = [
        $i,
        $names[$i % count($names)] . ' ' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
        $teams[$i % count($teams)],
        $statuses[$i % count($statuses)],
        $i % 5 === 0 ? null : ['low', 'medium', 'high'][$i % 3],
        $i % 11 === 0 ? null : 'owner-' . (($i % 17) + 1),
        $regions[$i % count($regions)],
        '2026-08-' . str_pad((string)(($i % 28) + 1), 2, '0', STR_PAD_LEFT),
        $i % 13 === 0 ? null : '2026-09-' . str_pad((string)(($i % 28) + 1), 2, '0', STR_PAD_LEFT),
        $i % 101,
        $i % 9 === 0 ? "first line\nsecond line for row {$i}" : 'single line note ' . $i,
        $i % 7 === 0 ? str_repeat('long detail ', 6) : 'detail ' . $i,
      ];
    }

    $table = new TableView('table-large-mixed', $header, $rows, [6, 16, 12, 12, 10, 12, 9, 12, 12, 8, 24, 28]);
    $table->setRowNumbers(true);
    return $table;
  }

  protected static function showPanel(ExampleHost $host, string $title, ListView|TableView $view, int $rows, string $size = 'normal'): void {
    $panel = $host->panel(strtolower(str_replace(' ', '-', $title)), $title, $size);
    $panel->addContent($view, $rows);
    $host->showPanel('Collections / ' . $title, $panel);
  }

}
