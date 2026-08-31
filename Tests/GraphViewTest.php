<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\GridBuffer;
use SPTK\Core\ImageRenderTarget;
use SPTK\Core\PixelTextRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Widgets\GraphView;

class GraphTargetProbe implements RenderTarget {

  public array $fills = [];
  public array $writes = [];
  public array $puts = [];
  public array $lines = [];
  public array $rects = [];
  public array $clips = [];

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $glyph = $value instanceof Cell ? $value->glyph : $value;
    $this->puts[] = [$x, $y, $glyph, Color::from($fg ?? '#ffffff')->hex(), Color::from($bg ?? '#000000')->hex()];
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->writes[] = [$x, $y, $text, Color::from($fg ?? '#ffffff')->hex(), Color::from($bg ?? '#000000')->hex()];
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fills[] = [$rect->x, $rect->y, $rect->width, $rect->height, Color::from($fg ?? '#ffffff')->hex(), Color::from($bg ?? '#000000')->hex()];
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->fill($rect, $value, $fg, $bg, $flags);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
    $this->lines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    $this->rects[] = [$rect->x, $rect->y, $rect->width, $rect->height, Color::from($color)->hex(), $thickness];
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
    $this->drawRect($rect, $color, $thickness);
  }

  public function pushClip(Rect $rect): void {
    $this->clips[] = [$rect->x, $rect->y, $rect->width, $rect->height];
  }

  public function popClip(): void {
  }

}

class GraphViewProbe extends GraphView {

  public function exposedRanges(): array {
    return $this->ranges();
  }

}

class GraphPixelTargetProbe extends GraphTargetProbe implements ImageRenderTarget, SurfaceRenderTarget, PixelTextRenderTarget {

  public array $pixelFills = [];
  public array $pixelLines = [];
  public array $pixelTexts = [];

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth()));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight, $this->cellHeight()));
  }

  public function cellWidth(): int {
    return 10;
  }

  public function cellHeight(): int {
    return 20;
  }

  public function currentSurfacePixelRect(): Rect {
    return new Rect(0, 0, 400, 240);
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
  }

  public function popSurface(): void {
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
    $this->pixelFills[] = [$pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height, Color::from($color)->hex()];
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
    $this->pixelLines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
  }

  public function pixelRectForCells(Rect $rect): Rect {
    return new Rect($rect->x * $this->cellWidth(), $rect->y * $this->cellHeight(), $rect->width * $this->cellWidth(), $rect->height * $this->cellHeight());
  }

  public function drawImagePixels(\GdImage $image, Rect $sourceRect, Rect $targetRect): void {
  }

  public function measureTextPixels(string $text, array $font = []): array {
    return [mb_strlen($text) * 6, 12];
  }

  public function fontMetricsPixels(array $font = []): array {
    return ['ascent' => 10, 'descent' => 2, 'height' => 12];
  }

  public function drawTextPixels(string $text, Rect $targetRect, Color|string|int $color, array $font = []): void {
    $this->pixelTexts[] = [$text, $targetRect->x, $targetRect->y, $targetRect->width, $targetRect->height, Color::from($color)->hex()];
  }

}

return [
  'graph view stores normalized series' => function(): void {
    $graph = new GraphView('graph');
    $graph->addSeries([
      'name' => 'Revenue',
      'type' => 'line',
      'points' => [[1, 2], ['bad', 4], [2, 3]],
      'color' => '#123456',
    ]);

    $series = $graph->series();
    assertSame(1, count($series), 'GraphView should store one added series.');
    assertSame('Revenue', $series[0]['name'], 'GraphView should keep the series name.');
    assertSame('line', $series[0]['type'], 'GraphView should keep a supported series type.');
    assertSame([[1.0, 2.0], [2.0, 3.0]], $series[0]['points'], 'GraphView should ignore invalid points and cast numeric points.');
    assertSame('#123456', $series[0]['color'], 'GraphView should keep explicit series colors.');
  },

  'graph view autoscale includes zero for bars' => function(): void {
    $graph = new GraphViewProbe('graph', [[
      'type' => 'bar',
      'points' => [[1, 5], [2, 8]],
    ]]);

    $ranges = $graph->exposedRanges();
    assertSame(0.0, $ranges['yMin'], 'Bar autoscale should include a zero baseline.');
    assertSame(8.0, $ranges['yMax'], 'Bar autoscale should keep the maximum Y value.');
  },

  'graph view manual ranges override autoscale' => function(): void {
    $graph = new GraphViewProbe('graph', [[
      'points' => [[10, 20], [30, 40]],
    ]], [
      'xMin' => 0,
      'xMax' => 100,
      'yMin' => -10,
      'yMax' => 50,
    ]);

    $ranges = $graph->exposedRanges();
    assertSame(0.0, $ranges['xMin'], 'Manual xMin should override data.');
    assertSame(100.0, $ranges['xMax'], 'Manual xMax should override data.');
    assertSame(-10.0, $ranges['yMin'], 'Manual yMin should override data.');
    assertSame(50.0, $ranges['yMax'], 'Manual yMax should override data.');
  },

  'graph view renders axis labels and units' => function(): void {
    $graph = new GraphView('graph', [[
      'type' => 'point',
      'points' => [[0, 0], [10, 20]],
    ]], [
      'xLabel' => 'Time',
      'yLabel' => 'Load',
      'xUnit' => 's',
      'yUnit' => '%',
      'legend' => false,
    ]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $buffer = new GridBuffer(30, 10);
    $graph->render($buffer);

    assertTrue(str_contains($buffer->line(0), 'Load'), 'Y axis label should render near the plot.');
    assertTrue(str_contains($buffer->line(8), '0s'), 'X axis unit should render in tick labels.');
    assertTrue(str_contains($buffer->line(7), '0%'), 'Y axis unit should render in tick labels.');
    assertTrue(str_contains($buffer->line(6), 'Time'), 'X axis label should render inside the plot near the axis edge.');
  },

  'graph view emits primitives for line point and bar series' => function(): void {
    $graph = new GraphView('graph', [
      ['name' => 'L', 'type' => 'line', 'points' => [[0, 0], [1, 1]], 'color' => '#111111'],
      ['name' => 'P', 'type' => 'point', 'points' => [[0.5, 0.5]], 'color' => '#222222'],
      ['name' => 'B', 'type' => 'bar', 'points' => [[0, 1]], 'color' => '#333333'],
    ]);
    $graph->setFrame(new Rect(0, 0, 40, 12));
    $target = new GraphTargetProbe();
    $graph->render($target);

    assertTrue(count($target->lines) >= 3, 'GraphView should draw axes and line-series segments.');
    assertTrue(count($target->puts) >= 3, 'GraphView should draw point markers as glyphs.');
    assertTrue(count($target->fills) >= 2, 'GraphView should fill the background and bar rectangles.');
    assertSame('#333333', $target->fills[count($target->fills) - 1][5], 'Bar fill should use the series color as background.');
  },

  'graph view uses pixel filled squares for point markers when available' => function(): void {
    $graph = new GraphView('graph', [[
      'name' => 'P',
      'type' => 'point',
      'points' => [[0, 0], [1, 1]],
      'color' => '#222222',
    ]]);
    $graph->setFrame(new Rect(0, 0, 40, 12));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    assertSame(2, count($target->pixelFills), 'Point markers should use pixel fills when the target supports them.');
    assertSame([4, 4], [$target->pixelFills[0][2], $target->pixelFills[0][3]], 'Pixel point markers should be small filled squares.');
    assertSame('#222222', $target->pixelFills[0][4], 'Pixel point markers should use the series color.');
    assertSame(0, count($target->puts), 'Pixel point markers should not fall back to glyphs.');
  },

  'graph view keeps pixel bars inside the plot with consistent widths' => function(): void {
    $graph = new GraphView('graph', [[
      'name' => 'Bars',
      'type' => 'bar',
      'points' => [[1, 8], [2, 13], [3, 5], [4, 17], [5, 11], [6, 20]],
      'color' => '#55ff55',
    ]]);
    $graph->setFrame(new Rect(0, 0, 40, 12));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    assertSame(6, count($target->pixelFills), 'Each bar should render as one pixel fill.');
    foreach ($target->pixelFills as $fill) {
      assertTrue($fill[0] >= 40, 'Bars should not start left of the plot area.');
      assertTrue($fill[0] + $fill[2] <= 390, 'Bars should not extend right of the plot area.');
      assertSame(40, $fill[2], 'Bars should use the same pixel width across the series.');
    }
  },

  'graph view uses pixel text and tick marks when available' => function(): void {
    $graph = new GraphView('graph', [[
      'type' => 'line',
      'points' => [[0, 0], [10, 20]],
      'color' => '#00ffff',
    ]], [
      'xLabel' => 'Time',
      'yLabel' => 'Load',
      'xUnit' => 's',
      'yUnit' => '%',
      'legend' => false,
    ]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    $texts = array_map(fn($text) => $text[0], $target->pixelTexts);
    assertTrue(in_array('Time', $texts, true), 'X axis label should render as pixel text.');
    assertTrue(in_array('Load', $texts, true), 'Y axis label should render as pixel text.');
    assertTrue(in_array('0s', $texts, true), 'X tick labels should render as pixel text with units.');
    assertTrue(in_array('0%', $texts, true), 'Y tick labels should render as pixel text with units.');
    assertTrue(count($target->pixelLines) >= 10, 'Tick marks should render as pixel scale marks on both axes.');
    assertSame(0, count($target->writes), 'Pixel-capable targets should not use grid-aligned label writes.');
    foreach ($target->pixelTexts as $text) {
      if ($text[0] === '0s') {
        assertSame(146, $text[2], 'X tick labels should render directly under the axis tick marks.');
      }
    }
  },

  'graph view keeps explicit title legend and y label in separate areas' => function(): void {
    $graph = new GraphView('graph', [[
      'name' => 'Requests',
      'type' => 'line',
      'points' => [[0, 1], [1, 2]],
      'color' => '#00ffff',
    ]], [
      'title' => 'Requests',
      'yLabel' => 'Requests',
    ]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    $pixelTexts = array_map(fn($text) => $text[0], $target->pixelTexts);
    assertTrue(in_array('Requests', $pixelTexts, true), 'Y axis label should still render even when it matches the series name.');
    assertSame(['Requests', 126, 4, 48, 12, '#aaaaaa'], $target->pixelTexts[11], 'Explicit title should be pixel centered at the top.');
    assertSame([' Requests ', 230, 160, 60, 12, '#00ffff'], $target->pixelTexts[12], 'Single-series legend should render as pixel text directly under the X values aligned to the plot edge.');
    assertSame(0, count($target->writes), 'Pixel-capable graph title and legend should not use grid writes.');
  },

  'graph view does not create implicit title from single series name' => function(): void {
    $graph = new GraphView('graph', [[
      'name' => 'Requests',
      'type' => 'line',
      'points' => [[0, 1], [1, 2]],
      'color' => '#00ffff',
    ]]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $target = new GraphTargetProbe();
    $graph->render($target);

    assertTrue($target->writes[0][2] !== 'Requests', 'Single series name should not render as an automatic title.');
    assertSame(' Requests ', $target->writes[count($target->writes) - 1][2], 'Series name should still render in the legend.');
  },

  'graph view places multi series legend under graph right aligned' => function(): void {
    $graph = new GraphView('graph', [
      ['name' => 'A', 'type' => 'line', 'points' => [[0, 1], [1, 2]], 'color' => '#111111'],
      ['name' => 'B', 'type' => 'point', 'points' => [[0, 1]], 'color' => '#222222'],
    ]);
    $graph->setFrame(new Rect(0, 0, 20, 8));
    $target = new GraphTargetProbe();
    $graph->render($target);

    assertSame([13, 7, ' A ', '#111111', '#0000aa'], $target->writes[count($target->writes) - 2], 'First legend item should start at the right-aligned legend block.');
    assertSame([16, 7, ' B ', '#222222', '#0000aa'], $target->writes[count($target->writes) - 1], 'Second legend item should continue on the bottom legend row.');
  },

  'graph view aligns y label with y tick value start' => function(): void {
    $graph = new GraphView('graph', [[
      'type' => 'line',
      'points' => [[0, 0], [10, 20]],
    ]], [
      'yLabel' => 'Load',
      'yUnit' => '%',
      'legend' => false,
    ]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    $positions = [];
    foreach ($target->pixelTexts as $text) {
      $positions[$text[0]] = $text[1];
    }
    assertSame($positions['0%'], $positions['Load'], 'Y label should start where Y tick values start.');
  },

  'graph view y label width does not widen value gutter' => function(): void {
    $short = new GraphView('graph-short', [[
      'type' => 'line',
      'points' => [[0, 0], [10, 20]],
    ]], [
      'yLabel' => 'Load',
      'yUnit' => '%',
      'legend' => false,
    ]);
    $short->setFrame(new Rect(0, 0, 30, 10));
    $shortTarget = new GraphPixelTargetProbe();
    $short->render($shortTarget);

    $long = new GraphView('graph-long', [[
      'type' => 'line',
      'points' => [[0, 0], [10, 20]],
    ]], [
      'yLabel' => 'Very Long Load Label',
      'yUnit' => '%',
      'legend' => false,
    ]);
    $long->setFrame(new Rect(0, 0, 30, 10));
    $longTarget = new GraphPixelTargetProbe();
    $long->render($longTarget);

    $shortPositions = [];
    foreach ($shortTarget->pixelTexts as $text) {
      $shortPositions[$text[0]] = $text[1];
    }
    $longPositions = [];
    foreach ($longTarget->pixelTexts as $text) {
      $longPositions[$text[0]] = $text[1];
    }
    assertSame($longPositions['0%'], $longPositions['Very Long Load Label'], 'Long Y label should start at the tick value column.');
    assertSame($shortPositions['0%'], $longPositions['0%'], 'Y tick value column should be based on tick width, not Y label width.');
  },

  'graph view places x label inside plot right aligned to edge' => function(): void {
    $graph = new GraphView('graph', [[
      'type' => 'bar',
      'points' => [[1, 8], [2, 13], [3, 5]],
    ]], [
      'xLabel' => 'Batch',
      'legend' => false,
    ]);
    $graph->setFrame(new Rect(0, 0, 30, 10));
    $target = new GraphPixelTargetProbe();
    $graph->render($target);

    foreach ($target->pixelTexts as $text) {
      if ($text[0] === 'Batch') {
        assertSame(260, $text[1], 'X axis label should be right aligned to the plot edge.');
        assertTrue($text[2] < 140, 'X axis label should sit above the axis inside the plot area.');
        return;
      }
    }
    assertTrue(false, 'X axis label should render.');
  },

  'graph view tolerates empty data and tiny frames' => function(): void {
    $graph = new GraphView('graph', [[
      'points' => [],
    ]]);
    $graph->setFrame(new Rect(0, 0, 3, 2));
    $target = new GraphTargetProbe();
    $graph->render($target);

    assertSame(1, count($target->fills), 'Tiny graph should only clear its background.');
    assertSame(0, count($target->lines), 'Tiny graph should skip axes.');
  },
];
