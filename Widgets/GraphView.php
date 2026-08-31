<?php

namespace SPTK\Widgets;

use SPTK\Core\ImageRenderTarget;
use SPTK\Core\PixelTextRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Element;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Displays numeric XY data as point, line, or bar series.
 */
class GraphView extends Element {

  protected array $series = [];
  protected string $xLabel = '';
  protected string $yLabel = '';
  protected string $title = '';
  protected string $xUnit = '';
  protected string $yUnit = '';
  protected ?float $xMin = null;
  protected ?float $xMax = null;
  protected ?float $yMin = null;
  protected ?float $yMax = null;
  protected int $tickCount = 5;
  protected bool $legend = true;
  protected array $palette = [
    '#00ffff',
    '#ffff00',
    '#ff55ff',
    '#55ff55',
    '#ff5555',
    '#ffffff',
  ];

  public function __construct(string $name = '', array $series = [], array $options = []) {
    parent::__construct($name);
    $this->setPreferredRows(12);
    $this->setPreferredColumns(40);
    $this->setOptions($options);
    $this->setSeries($series);
  }

  public function setSeries(array $series): static {
    $this->series = [];
    foreach ($series as $item) {
      if (is_array($item)) {
        $this->series[] = $this->normalizeSeries($item, count($this->series));
      }
    }
    $this->invalidateRender();
    return $this;
  }

  public function addSeries(array $series): static {
    $this->series[] = $this->normalizeSeries($series, count($this->series));
    $this->invalidateRender();
    return $this;
  }

  public function series(): array {
    return $this->series;
  }

  public function setOptions(array $options): static {
    if (array_key_exists('xLabel', $options)) {
      $this->xLabel = (string)$options['xLabel'];
    }
    if (array_key_exists('yLabel', $options)) {
      $this->yLabel = (string)$options['yLabel'];
    }
    if (array_key_exists('title', $options)) {
      $this->title = (string)$options['title'];
    }
    if (array_key_exists('xUnit', $options)) {
      $this->xUnit = (string)$options['xUnit'];
    }
    if (array_key_exists('yUnit', $options)) {
      $this->yUnit = (string)$options['yUnit'];
    }
    foreach (['xMin', 'xMax', 'yMin', 'yMax'] as $key) {
      if (array_key_exists($key, $options)) {
        $this->{$key} = is_numeric($options[$key]) ? (float)$options[$key] : null;
      }
    }
    if (array_key_exists('tickCount', $options)) {
      $this->tickCount = max(2, min(12, (int)$options['tickCount']));
    }
    if (array_key_exists('legend', $options)) {
      $this->legend = $this->boolOption($options['legend']);
    }
    $this->invalidateRender();
    return $this;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    if ($this->frame->width < 6 || $this->frame->height < 4) {
      return;
    }

    $ranges = $this->ranges();
    $plot = $this->plotRect($ranges);
    if ($plot->width < 2 || $plot->height < 2) {
      return;
    }

    $this->paintAxes($target, $plot);
    $this->paintTicks($target, $plot, $ranges);
    foreach ($this->series as $series) {
      $this->paintSeries($target, $plot, $ranges, $series);
    }
    $this->paintLabels($target, $plot);
    $this->paintTitle($target, $plot);
    if ($this->legend) {
      $this->paintLegend($target, $plot);
    }
  }

  protected function normalizeSeries(array $series, int $index): array {
    $type = (string)($series['type'] ?? 'line');
    if (!in_array($type, ['line', 'point', 'bar'], true)) {
      $type = 'line';
    }

    $points = [];
    foreach ((array)($series['points'] ?? []) as $point) {
      if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
        continue;
      }
      $points[] = [(float)$point[0], (float)$point[1]];
    }

    return [
      'name' => (string)($series['name'] ?? 'Series ' . ($index + 1)),
      'type' => $type,
      'points' => $points,
      'color' => (string)($series['color'] ?? $this->palette[$index % count($this->palette)]),
    ];
  }

  protected function ranges(): array {
    $xs = [];
    $ys = [];
    $barXs = [];
    $hasBars = false;
    foreach ($this->series as $series) {
      $hasBars = $hasBars || $series['type'] === 'bar';
      foreach ($series['points'] as $point) {
        $xs[] = $point[0];
        $ys[] = $point[1];
        if ($series['type'] === 'bar') {
          $barXs[] = $point[0];
        }
      }
    }

    $xMin = $this->xMin ?? ($xs === [] ? 0.0 : min($xs));
    $xMax = $this->xMax ?? ($xs === [] ? 1.0 : max($xs));
    $yMin = $this->yMin ?? ($ys === [] ? 0.0 : min($ys));
    $yMax = $this->yMax ?? ($ys === [] ? 1.0 : max($ys));
    if ($hasBars && $this->yMin === null && $this->yMax === null) {
      $yMin = min(0.0, $yMin);
      $yMax = max(0.0, $yMax);
    }
    if ($hasBars && $this->xMin === null && $this->xMax === null) {
      $padding = $this->barXPadding($barXs);
      $xMin -= $padding;
      $xMax += $padding;
    }

    if ($xMin === $xMax) {
      $xMin -= 1.0;
      $xMax += 1.0;
    }
    if ($yMin === $yMax) {
      $yMin -= 1.0;
      $yMax += 1.0;
    }

    return ['xMin' => $xMin, 'xMax' => $xMax, 'yMin' => $yMin, 'yMax' => $yMax];
  }

  protected function plotRect(array $ranges): Rect {
    $leftGutterWidth = $this->yTickLabelWidth($ranges);
    $maxLeft = max(4, min(18, $this->frame->width - 8));
    $left = min($maxLeft, max(4, $leftGutterWidth + 2));
    $bottom = 2;
    $top = ($this->chartTitle() !== '' ? 1 : 0) + ($this->yLabel !== '' ? 1 : 0);
    $right = 1;

    return $this->frame->inset($left, $top, $right, $bottom);
  }

  protected function paintAxes(RenderTarget $target, Rect $plot): void {
    $axisY = $plot->bottom() - 1;
    $target->drawLine($plot->x, $plot->y, $plot->x, $axisY, $this->theme->border, 1);
    $target->drawLine($plot->x, $axisY, $plot->right(), $axisY, $this->theme->border, 1);
  }

  protected function paintTicks(RenderTarget $target, Rect $plot, array $ranges): void {
    if ($target instanceof PixelTextRenderTarget && $target instanceof SurfaceRenderTarget && $target instanceof ImageRenderTarget) {
      $this->paintPixelTicks($target, $plot, $ranges);
      return;
    }

    $lastYLabel = null;
    $lastXLabel = null;
    $axisY = $plot->bottom() - 1;
    for ($i = 0; $i < $this->tickCount; $i++) {
      $ratio = $this->tickCount === 1 ? 0.0 : $i / ($this->tickCount - 1);
      $yValue = $ranges['yMin'] + ($ranges['yMax'] - $ranges['yMin']) * $ratio;
      $y = (int)round(($plot->bottom() - 1) - $ratio * max(1, $plot->height - 1));
      $target->drawLine($plot->x - 0.35, $y, $plot->x, $y, $this->theme->border, 1);
      $label = $this->formatTick($yValue, $this->yUnit);
      if ($label !== $lastYLabel) {
        $target->write($this->yLabelGutterX($plot, $ranges), $y, $label, $this->theme->muted, $this->theme->bg);
        $lastYLabel = $label;
      }

      $xValue = $ranges['xMin'] + ($ranges['xMax'] - $ranges['xMin']) * $ratio;
      $x = (int)round($plot->x + $ratio * max(1, $plot->width - 1));
      $target->drawLine($x, $axisY, $x, $axisY + 0.35, $this->theme->border, 1);
      $label = $this->formatTick($xValue, $this->xUnit);
      if ($label !== $lastXLabel) {
        $target->write($this->clampLabelX($x - intdiv(mb_strlen($label), 2), mb_strlen($label)), $plot->bottom(), $label, $this->theme->muted, $this->theme->bg);
        $lastXLabel = $label;
      }
    }
  }

  protected function paintPixelTicks(PixelTextRenderTarget&SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot, array $ranges): void {
    $lastYLabel = null;
    $lastXLabel = null;
    $axisY = $plot->bottom() - 1;
    $framePixels = $target->pixelRectForCells($this->frame);
    $plotLeft = $this->cellXToPixel($target, $plot->x);
    $axisPixelY = $this->cellYToPixel($target, $axisY);
    $tickPixels = max(3, (int)round(min($target->cellWidth(), $target->cellHeight()) * 0.35));
    $yGutterPixelWidth = $this->yTickLabelWidth($ranges) * $target->cellWidth();
    $yGutterPixelX = max($framePixels->x, (int)round($plotLeft - $tickPixels - $yGutterPixelWidth - 3));

    for ($i = 0; $i < $this->tickCount; $i++) {
      $ratio = $this->tickCount === 1 ? 0.0 : $i / ($this->tickCount - 1);
      $yValue = $ranges['yMin'] + ($ranges['yMax'] - $ranges['yMin']) * $ratio;
      $y = ($plot->bottom() - 1) - $ratio * max(1, $plot->height - 1);
      $pixelY = $this->cellYToPixel($target, $y);
      $target->drawPixelLine($plotLeft - $tickPixels, $pixelY, $plotLeft, $pixelY, $this->theme->border, 1);
      $label = $this->formatTick($yValue, $this->yUnit);
      if ($label !== $lastYLabel) {
        [$width, $height] = $target->measureTextPixels($label);
        $target->drawTextPixels($label, new Rect($yGutterPixelX, (int)round($pixelY - $height / 2), $width, $height), $this->theme->muted);
        $lastYLabel = $label;
      }

      $xValue = $ranges['xMin'] + ($ranges['xMax'] - $ranges['xMin']) * $ratio;
      $x = $plot->x + $ratio * max(1, $plot->width - 1);
      $pixelX = $this->cellXToPixel($target, $x);
      $target->drawPixelLine($pixelX, $axisPixelY, $pixelX, $axisPixelY + $tickPixels, $this->theme->border, 1);
      $label = $this->formatTick($xValue, $this->xUnit);
      if ($label !== $lastXLabel) {
        [$width, $height] = $target->measureTextPixels($label);
        $labelX = $this->clampInt((int)round($pixelX - $width / 2), $framePixels->x, $framePixels->right() - $width);
        $labelY = $this->pixelXTickLabelY($target, $plot, $height);
        $target->drawTextPixels($label, new Rect($labelX, $labelY, $width, $height), $this->theme->muted);
        $lastXLabel = $label;
      }
    }
  }

  protected function paintLabels(RenderTarget $target, Rect $plot): void {
    if ($target instanceof PixelTextRenderTarget && $target instanceof SurfaceRenderTarget && $target instanceof ImageRenderTarget) {
      $this->paintPixelLabels($target, $plot);
      return;
    }

    if ($this->yLabel !== '') {
      $ranges = $this->ranges();
      $x = $this->yLabelGutterX($plot, $ranges);
      $target->write($x, $plot->y - 1, mb_substr($this->yLabel, 0, max(0, $this->frame->right() - $x)), $this->theme->fg, $this->theme->bg);
    }
    if ($this->xLabel !== '') {
      $label = mb_substr($this->xLabel, 0, $this->frame->width);
      $x = max($this->frame->x, $plot->right() - mb_strlen($label));
      $target->write($x, max($plot->y, $plot->bottom() - 2), $label, $this->theme->fg, $this->theme->bg);
    }
  }

  protected function paintPixelLabels(PixelTextRenderTarget&SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot): void {
    $framePixels = $target->pixelRectForCells($this->frame);
    if ($this->yLabel !== '') {
      $label = $this->yLabel;
      [$width, $height] = $target->measureTextPixels($label);
      $plotLeft = $this->cellXToPixel($target, $plot->x);
      $ranges = $this->ranges();
      $tickPixels = max(3, (int)round(min($target->cellWidth(), $target->cellHeight()) * 0.35));
      $x = max($framePixels->x, (int)round($plotLeft - $this->yTickLabelWidth($ranges) * $target->cellWidth() - $tickPixels - 3));
      $y = $this->cellYToPixel($target, $plot->y - 1);
      $target->drawTextPixels($label, new Rect($x, (int)round($y), $width, $height), $this->theme->fg);
    }
    if ($this->xLabel !== '') {
      $label = $this->xLabel;
      [$width, $height] = $target->measureTextPixels($label);
      $plotRight = $this->cellXToPixel($target, $plot->right());
      $axisY = $this->cellYToPixel($target, $plot->bottom() - 1);
      $x = $this->clampInt((int)round($plotRight - $width), $framePixels->x, $framePixels->right() - $width);
      $y = max($framePixels->y, (int)round($axisY - $height - 2));
      $target->drawTextPixels($label, new Rect($x, $y, $width, $height), $this->theme->fg);
    }
  }

  protected function paintTitle(RenderTarget $target, Rect $plot): void {
    $title = $this->chartTitle();
    if ($title === '' || $this->frame->height < 1) {
      return;
    }
    if ($target instanceof PixelTextRenderTarget && $target instanceof SurfaceRenderTarget && $target instanceof ImageRenderTarget) {
      $this->paintPixelTitle($target, $title);
      return;
    }
    $title = mb_substr($title, 0, $this->frame->width);
    $x = $this->frame->x + max(0, intdiv($this->frame->width - mb_strlen($title), 2));
    $target->write($x, $this->frame->y, $title, $this->titleColor(), $this->theme->bg);
  }

  protected function paintPixelTitle(PixelTextRenderTarget&SurfaceRenderTarget&ImageRenderTarget $target, string $title): void {
    $framePixels = $target->pixelRectForCells($this->frame);
    [$width, $height] = $target->measureTextPixels($title);
    $x = $this->clampInt((int)round($framePixels->x + ($framePixels->width - $width) / 2), $framePixels->x, $framePixels->right() - $width);
    $y = $framePixels->y + intdiv(max(0, $target->cellHeight() - $height), 2);
    $target->drawTextPixels($title, new Rect($x, $y, $width, $height), $this->titleColor());
  }

  protected function chartTitle(): string {
    if ($this->title !== '') {
      return $this->title;
    }
    return '';
  }

  protected function titleColor(): string {
    return $this->theme->fg->hex();
  }

  protected function paintLegend(RenderTarget $target, Rect $plot): void {
    if ($this->series === [] || $this->frame->height < 2) {
      return;
    }
    if ($target instanceof PixelTextRenderTarget && $target instanceof SurfaceRenderTarget && $target instanceof ImageRenderTarget) {
      $this->paintPixelLegend($target, $plot);
      return;
    }

    $legendWidth = 0;
    foreach ($this->series as $series) {
      $legendWidth += mb_strlen(' ' . $series['name'] . ' ');
    }
    $x = max($this->frame->x, $plot->right() - $legendWidth);
    $y = $this->frame->bottom() - 1;
    foreach ($this->series as $series) {
      $text = ' ' . $series['name'] . ' ';
      if ($x + mb_strlen($text) > $plot->right()) {
        break;
      }
      $target->write($x, $y, $text, $series['color'], $this->theme->bg);
      $x += mb_strlen($text);
    }
  }

  protected function paintPixelLegend(PixelTextRenderTarget&SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot): void {
    $items = [];
    $width = 0;
    foreach ($this->series as $series) {
      $text = ' ' . $series['name'] . ' ';
      [$itemWidth, $itemHeight] = $target->measureTextPixels($text);
      $items[] = [$series, $text, $itemWidth, $itemHeight];
      $width += $itemWidth;
    }
    if ($items === []) {
      return;
    }
    $framePixels = $target->pixelRectForCells($this->frame);
    $plotRight = $this->cellXToPixel($target, $plot->right());
    $x = $this->clampInt((int)round($plotRight - $width), $framePixels->x, $framePixels->right() - $width);
    foreach ($items as [$series, $text, $itemWidth, $itemHeight]) {
      $y = $this->pixelLegendY($target, $plot, $itemHeight);
      $target->drawTextPixels($text, new Rect($x, $y, $itemWidth, $itemHeight), $series['color']);
      $x += $itemWidth;
    }
  }

  protected function paintSeries(RenderTarget $target, Rect $plot, array $ranges, array $series): void {
    if ($series['points'] === []) {
      return;
    }
    if ($series['type'] === 'bar') {
      $this->paintBars($target, $plot, $ranges, $series);
      return;
    }

    $mapped = array_map(fn($point) => $this->mapPoint($point, $plot, $ranges), $series['points']);
    if ($series['type'] === 'line') {
      for ($i = 1; $i < count($mapped); $i++) {
        $target->drawLine($mapped[$i - 1][0], $mapped[$i - 1][1], $mapped[$i][0], $mapped[$i][1], $series['color'], 2);
      }
    }
    foreach ($mapped as $point) {
      $this->paintPointMarker($target, $point[0], $point[1], $series['color']);
    }
  }

  protected function paintPointMarker(RenderTarget $target, float $x, float $y, string $color): void {
    if ($target instanceof ImageRenderTarget && $target instanceof SurfaceRenderTarget) {
      $pixelX = $this->cellXToPixel($target, $x);
      $pixelY = $this->cellYToPixel($target, $y);
      $size = max(3, min(7, (int)round(min($target->cellWidth(), $target->cellHeight()) * 0.35)));
      $target->fillPixels(new Rect((int)round($pixelX) - intdiv($size, 2), (int)round($pixelY) - intdiv($size, 2), $size, $size), $color);
      return;
    }
    $target->put((int)round($x), (int)round($y), '*', $color, $this->theme->bg);
  }

  protected function paintBars(RenderTarget $target, Rect $plot, array $ranges, array $series): void {
    $barWidth = $this->barWidth($series['points'], $plot, $ranges);
    $base = $this->clampFloat($this->mapY(0.0, $plot, $ranges), $plot->y, $plot->bottom() - 1);
    if ($target instanceof ImageRenderTarget && $target instanceof SurfaceRenderTarget) {
      $this->paintPixelBars($target, $plot, $ranges, $series, $barWidth, $base);
      return;
    }

    foreach ($series['points'] as $point) {
      [$x, $y] = $this->mapPoint($point, $plot, $ranges);
      $y = $this->clampFloat($y, $plot->y, $plot->bottom() - 1);
      $left = (int)round($x - $barWidth / 2);
      $right = $left + $barWidth;
      $left = max($plot->x, $left);
      $right = min($plot->right(), $right);
      $top = max($plot->y, (int)floor(min($y, $base)));
      $bottom = min($plot->bottom(), (int)ceil(max($y, $base)));
      $target->fill(new Rect($left, $top, max(1, $right - $left), max(1, $bottom - $top)), ' ', $series['color'], $series['color']);
    }
  }

  protected function paintPixelBars(SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot, array $ranges, array $series, int $barWidth, float $base): void {
    $plotPixels = $target->pixelRectForCells($plot);
    $pixelBarWidth = max(2, (int)round($barWidth * $target->cellWidth()));
    $basePixelY = $this->cellYToPixel($target, $base);
    foreach ($series['points'] as $point) {
      [$x, $y] = $this->mapPoint($point, $plot, $ranges);
      $y = $this->clampFloat($y, $plot->y, $plot->bottom() - 1);
      $pixelX = $this->cellXToPixel($target, $x);
      $pixelY = $this->cellYToPixel($target, $y);
      $left = (int)round($pixelX - $pixelBarWidth / 2);
      $right = $left + $pixelBarWidth;
      $left = max($plotPixels->x, $left);
      $right = min($plotPixels->right(), $right);
      $top = max($plotPixels->y, (int)floor(min($pixelY, $basePixelY)));
      $bottom = min($plotPixels->bottom(), (int)ceil(max($pixelY, $basePixelY)));
      $target->fillPixels(new Rect($left, $top, max(1, $right - $left), max(1, $bottom - $top)), $series['color']);
    }
  }

  protected function barWidth(array $points, Rect $plot, array $ranges): int {
    if (count($points) < 2) {
      return max(1, (int)floor($plot->width * 0.6));
    }
    $xs = [];
    foreach ($points as $point) {
      $xs[] = $this->mapX($point[0], $plot, $ranges);
    }
    sort($xs);
    $spacing = null;
    for ($i = 1; $i < count($xs); $i++) {
      $distance = $xs[$i] - $xs[$i - 1];
      if ($distance > 0.001) {
        $spacing = $spacing === null ? $distance : min($spacing, $distance);
      }
    }
    $spacing ??= max(1.0, $plot->width * 0.6);
    return max(1, (int)floor($spacing * 0.72));
  }

  protected function barXPadding(array $xs): float {
    if (count($xs) < 2) {
      return 0.5;
    }
    sort($xs);
    $spacing = null;
    for ($i = 1; $i < count($xs); $i++) {
      $distance = $xs[$i] - $xs[$i - 1];
      if ($distance > 0.001) {
        $spacing = $spacing === null ? $distance : min($spacing, $distance);
      }
    }
    return ($spacing ?? 1.0) / 2.0;
  }

  protected function mapPoint(array $point, Rect $plot, array $ranges): array {
    return [
      $this->mapX($point[0], $plot, $ranges),
      $this->mapY($point[1], $plot, $ranges),
    ];
  }

  protected function mapX(float $value, Rect $plot, array $ranges): float {
    $ratio = ($value - $ranges['xMin']) / ($ranges['xMax'] - $ranges['xMin']);
    return $plot->x + $ratio * max(1, $plot->width - 1);
  }

  protected function mapY(float $value, Rect $plot, array $ranges): float {
    $ratio = ($value - $ranges['yMin']) / ($ranges['yMax'] - $ranges['yMin']);
    return ($plot->bottom() - 1) - $ratio * max(1, $plot->height - 1);
  }

  protected function cellXToPixel(SurfaceRenderTarget&ImageRenderTarget $target, float $x): float {
    $framePixels = $target->pixelRectForCells($this->frame);
    return $framePixels->x + ($x - $this->frame->x) * $target->cellWidth();
  }

  protected function cellYToPixel(SurfaceRenderTarget&ImageRenderTarget $target, float $y): float {
    $framePixels = $target->pixelRectForCells($this->frame);
    return $framePixels->y + ($y - $this->frame->y) * $target->cellHeight();
  }

  protected function pixelXTickLabelY(SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot, int $height): int {
    $tickPixels = max(3, (int)round(min($target->cellWidth(), $target->cellHeight()) * 0.35));
    return (int)round($this->cellYToPixel($target, $plot->bottom() - 1) + $tickPixels + 2);
  }

  protected function pixelLegendY(SurfaceRenderTarget&ImageRenderTarget $target, Rect $plot, int $height): int {
    $framePixels = $target->pixelRectForCells($this->frame);
    $y = $this->pixelXTickLabelY($target, $plot, $height) + $height + 2;
    return min($y, $framePixels->bottom() - $height);
  }

  protected function clampFloat(float $value, float $min, float $max): float {
    return max($min, min($max, $value));
  }

  protected function formatTick(float $value, string $unit = ''): string {
    $rounded = abs($value) >= 10 ? round($value) : round($value, 1);
    $text = rtrim(rtrim(sprintf('%.1f', $rounded), '0'), '.');
    if ($text === '-0') {
      $text = '0';
    }
    return $text . $unit;
  }

  protected function yTickLabelWidth(array $ranges): int {
    $width = 0;
    for ($i = 0; $i < $this->tickCount; $i++) {
      $ratio = $this->tickCount === 1 ? 0.0 : $i / ($this->tickCount - 1);
      $value = $ranges['yMin'] + ($ranges['yMax'] - $ranges['yMin']) * $ratio;
      $width = max($width, mb_strlen($this->formatTick($value, $this->yUnit)));
    }
    return $width;
  }

  protected function yLabelGutterX(Rect $plot, array $ranges): int {
    return max($this->frame->x, $plot->x - $this->yTickLabelWidth($ranges) - 1);
  }

  protected function clampLabelX(int $x, int $width): int {
    return max($this->frame->x, min($x, $this->frame->right() - $width));
  }

  protected function clampInt(int $value, int $min, int $max): int {
    return max($min, min($max, $value));
  }

  protected function boolOption(mixed $value): bool {
    if (is_bool($value)) {
      return $value;
    }
    if (is_string($value)) {
      return !in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
    }
    return (bool)$value;
  }

}
