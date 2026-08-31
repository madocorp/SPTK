<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\Label;
use SPTK\Widgets\GraphView;
use SPTK\Widgets\GraphicsView;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\ProgressBar;

class FeedbackExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Feedback');
    $progress = new MenuItem('ProgressBar');
    $progress->addItem([
      'label' => 'Percent',
      'action' => function() use ($host): void {
        $panel = $host->panel('progress-percent-example', 'ProgressBar Percent', 'small');
        $panel->addContent(new ProgressBar('progress-percent', 'Loading records', 66, 100));
        $host->showPanel('Feedback / ProgressBar / Percent', $panel);
      },
    ]);
    $progress->addItem([
      'label' => 'Value',
      'action' => function() use ($host): void {
        $panel = $host->panel('progress-value-example', 'ProgressBar Value', 'small');
        $panel->addContent(new ProgressBar('progress-value', 'Copying rows', 3, 10, ['textMode' => 'value']));
        $host->showPanel('Feedback / ProgressBar / Value', $panel);
      },
    ]);
    $menu->addItem($progress);
    $graph = new MenuItem('GraphView');
    $graph->addItem([
      'label' => 'Line',
      'action' => fn() => $host->showMain('Feedback / GraphView / Line', self::lineGraph()),
    ]);
    $graph->addItem([
      'label' => 'Points',
      'action' => fn() => $host->showMain('Feedback / GraphView / Points', self::pointGraph()),
    ]);
    $graph->addItem([
      'label' => 'Bars',
      'action' => fn() => $host->showMain('Feedback / GraphView / Bars', self::barGraph()),
    ]);
    $graph->addItem([
      'label' => 'Mixed Series',
      'action' => fn() => $host->showMain('Feedback / GraphView / Mixed Series', self::mixedGraph()),
    ]);
    $graph->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showGraphPanel($host),
    ]);
    $menu->addItem($graph);
    $menu->addItem([
      'label' => 'GraphicsView',
      'action' => fn() => $host->showMain('Feedback / GraphicsView', self::graphicsView()),
    ]);
    $menu->addItem([
      'label' => 'StatusBar',
      'action' => fn() => $host->showMain('Feedback / StatusBar', new Label('status-demo', 'The live status bar is at the bottom of this window.')),
    ]);
    return $menu;
  }

  protected static function lineGraph(): GraphView {
    return new GraphView('graph-line', [[
      'name' => 'Requests',
      'type' => 'line',
      'points' => [[0, 18], [1, 22], [2, 31], [3, 28], [4, 44], [5, 52], [6, 49]],
      'color' => '#00ffff',
    ]], [
      'title' => 'Requests',
      'xLabel' => 'Hour',
      'yLabel' => 'Requests',
      'xUnit' => 'h',
      'tickCount' => 5,
    ]);
  }

  protected static function pointGraph(): GraphView {
    return new GraphView('graph-points', [[
      'name' => 'Latency',
      'type' => 'point',
      'points' => [[0, 42], [1, 38], [2, 57], [3, 63], [4, 49], [5, 45], [6, 54], [7, 47]],
      'color' => '#ffff00',
    ]], [
      'title' => 'Latency',
      'xLabel' => 'Sample',
      'yLabel' => 'Latency',
      'yUnit' => 'ms',
      'tickCount' => 5,
    ]);
  }

  protected static function barGraph(): GraphView {
    return new GraphView('graph-bars', [[
      'name' => 'Rows',
      'type' => 'bar',
      'points' => [[1, 8], [2, 13], [3, 5], [4, 17], [5, 11], [6, 20]],
      'color' => '#55ff55',
    ]], [
      'title' => 'Rows',
      'xLabel' => 'Batch',
      'yLabel' => 'Rows',
      'tickCount' => 5,
    ]);
  }

  protected static function mixedGraph(): GraphView {
    return new GraphView('graph-mixed', [
      [
        'name' => 'Target',
        'type' => 'line',
        'points' => [[0, 20], [1, 25], [2, 31], [3, 38], [4, 44], [5, 50]],
        'color' => '#00ffff',
      ],
      [
        'name' => 'Actual',
        'type' => 'point',
        'points' => [[0, 18], [1, 29], [2, 27], [3, 40], [4, 41], [5, 53]],
        'color' => '#ffff00',
      ],
      [
        'name' => 'Volume',
        'type' => 'bar',
        'points' => [[0, 9], [1, 14], [2, 12], [3, 18], [4, 21], [5, 24]],
        'color' => '#ff55ff',
      ],
    ], [
      'title' => 'Target vs Actual',
      'xLabel' => 'Step',
      'yLabel' => 'Value',
      'tickCount' => 6,
    ]);
  }

  protected static function showGraphPanel(ExampleHost $host): void {
    $panel = $host->panel('graph-panel-example', 'GraphView Panel', 'big');
    $panel->addContent(self::mixedGraph(), 12);
    $host->showPanel('Feedback / GraphView / Panel', $panel);
  }

  protected static function graphicsView(): GraphicsView {
    $atlas = null;
    return new GraphicsView('graphics-view', function($canvas, $target) use (&$atlas): void {
      $canvas->clear('#001018');
      if ($atlas === null) {
        $atlas = $target->createTexture(64, 64, 'transparent');
        $atlas->fillRect(4, 4, 56, 56, '#24515c');
        $atlas->drawRect(4, 4, 56, 56, '#7efcff', 2);
        $atlas->drawLine(8, 56, 56, 8, '#ffcc66', 3);
        $atlas->fillRect(24, 24, 16, 16, '#ff5f87');
      }
      for ($y = 0; $y < $canvas->height(); $y += 72) {
        for ($x = 0; $x < $canvas->width(); $x += 72) {
          $atlas->copyTo($canvas, $x + 4, $y + 4);
        }
      }
      $canvas->drawRect(0, 0, $canvas->width(), $canvas->height(), '#ffffff', 1);
    });
  }

}
